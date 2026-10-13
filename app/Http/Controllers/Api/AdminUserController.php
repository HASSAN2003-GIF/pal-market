<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierQuotation;
use App\Models\User;
use App\Services\AccountProvisioner;
use App\Services\AuditLogger;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private const FIELDS = ['id', 'name', 'email', 'phone', 'role', 'suspended_at', 'last_login_at', 'created_at'];

    public function __construct(
        private AuditLogger $audit,
        private AccountProvisioner $provisioner,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => ['nullable', 'in:active,suspended'],
            'sort' => ['nullable', 'in:oldest,newest'],
        ]);

        $users = User::query()
            ->select(self::FIELDS)
            ->when($validated['search'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', Paginated::like($term))
                ->orWhere('email', 'like', Paginated::like($term))
                ->orWhere('phone', 'like', Paginated::like($term))))
            ->when($validated['role'] ?? null, fn (Builder $q, $role) => $q->where('role', $role))
            ->when(($validated['status'] ?? null) === 'suspended', fn (Builder $q) => $q->whereNotNull('suspended_at'))
            ->when(($validated['status'] ?? null) === 'active', fn (Builder $q) => $q->whereNull('suspended_at'))
            ->orderBy('id', ($validated['sort'] ?? 'oldest') === 'newest' ? 'desc' : 'asc')
            ->paginate(Paginated::perPage($request, 25, 100));

        return response()->json([
            'users' => $users->items(),
            'meta' => $this->meta($users),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['buyerProfile', 'supplier']);

        return response()->json([
            'user' => [
                ...$user->only(self::FIELDS),
                'email_verified_at' => $user->email_verified_at,
                'google_linked' => $user->google_id !== null,
                'buyer_profile' => $user->buyerProfile,
                'supplier' => $user->supplier,
                'active_tokens' => $user->tokens()->count(),
            ],
        ]);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        $this->guardSelf($request, $user, 'You cannot change your own role.');

        if ($user->isAdmin() && $validated['role'] !== 'admin') {
            $this->guardLastAdmin($user, 'You cannot demote the last active administrator.');
        }

        $previous = $user->role;

        DB::transaction(function () use ($user, $validated) {
            $user->forceFill(['role' => $validated['role']])->save();
            $this->provisioner->ensureProfile($user);
        });

        if ($previous !== $user->role) {
            // Permissions changed: force a fresh sign-in.
            $user->tokens()->delete();
            $this->audit->record('user.role_changed', "Changed {$user->email} from {$previous} to {$user->role}.", $user, [
                'from' => $previous,
                'to' => $user->role,
            ]);
        }

        return response()->json([
            'message' => __('Role updated.'),
            'user' => $user->only(self::FIELDS),
        ]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->guardSelf($request, $user, 'You cannot suspend your own account.');

        if ($user->isSuspended()) {
            return response()->json(['message' => __('This account is already suspended.')], 422);
        }

        if ($user->isAdmin()) {
            $this->guardLastAdmin($user, 'You cannot suspend the last active administrator.');
        }

        $user->forceFill(['suspended_at' => now()])->save();
        $user->tokens()->delete();

        $this->audit->record('user.suspended', "Suspended {$user->email}.", $user, [
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => __('Account suspended and signed out everywhere.'),
            'user' => $user->only(self::FIELDS),
        ]);
    }

    public function reactivate(User $user): JsonResponse
    {
        if (! $user->isSuspended()) {
            return response()->json(['message' => __('This account is not suspended.')], 422);
        }

        $user->forceFill(['suspended_at' => null])->save();

        $this->audit->record('user.reactivated', "Reactivated {$user->email}.", $user);

        return response()->json([
            'message' => __('Account reactivated.'),
            'user' => $user->only(self::FIELDS),
        ]);
    }

    /**
     * Deletion is reserved for accounts without trading history; accounts that
     * have quotations or orders must be suspended instead so records survive.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guardSelf($request, $user, 'You cannot delete your own account.');

        if ($user->isAdmin()) {
            $this->guardLastAdmin($user, 'You cannot delete the last active administrator.');
        }

        $user->load(['buyerProfile', 'supplier']);

        $hasHistory = ($user->buyerProfile && (
            PurchaseOrder::where('buyer_profile_id', $user->buyerProfile->id)->exists()
            // Deleting the buyer would cascade-delete suppliers' bids too.
            || SupplierQuotation::whereNot('status', 'draft')
                ->whereHas('buyerRequest', fn (Builder $q) => $q->where('buyer_profile_id', $user->buyerProfile->id))
                ->exists()
        ))
            || ($user->supplier && (
                PurchaseOrder::where('supplier_id', $user->supplier->id)->exists()
                || SupplierQuotation::where('supplier_id', $user->supplier->id)->whereNot('status', 'draft')->exists()
            ));

        if ($hasHistory) {
            return response()->json([
                'message' => __('This account has trading history and cannot be deleted. Suspend it instead.'),
            ], 409);
        }

        $email = $user->email;
        $user->tokens()->delete();
        $user->delete();

        $this->audit->record('user.deleted', "Deleted account {$email}.", null, ['user_id' => $user->id]);

        return response()->json([
            'message' => __('User deleted successfully.'),
        ]);
    }

    private function guardSelf(Request $request, User $user, string $message): void
    {
        abort_if($request->user()->is($user), 422, __($message));
    }

    private function guardLastAdmin(User $user, string $message): void
    {
        $otherActiveAdmins = User::where('role', 'admin')
            ->whereNull('suspended_at')
            ->whereKeyNot($user->id)
            ->exists();

        abort_unless($otherActiveAdmins, 422, __($message));
    }

    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
