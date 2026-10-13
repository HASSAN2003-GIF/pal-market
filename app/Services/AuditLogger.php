<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes append-only audit records for security-relevant and administrative
 * actions. Never pass passwords, tokens or codes in $metadata.
 */
class AuditLogger
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'token', 'code', 'secret'];

    public function record(
        string $action,
        string $description,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= auth()->user();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_label' => $actor ? "{$actor->name} <{$actor->email}>" : 'system',
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'metadata' => $this->scrub($metadata) ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    private function scrub(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $metadata[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->scrub($value);
            }
        }

        return $metadata;
    }
}
