<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * First administrator on hosts without a server shell (e.g. Render's free
 * plan). Runs on every container start and does nothing once the admin has
 * signed in.
 *
 * - ADMIN_EMAIL names the account. If no account uses it, an administrator is
 *   created with a random password that nobody knows.
 * - Until that administrator first signs in, each start prints a one-time
 *   link (valid 60 minutes) to the deploy log, where the owner sets their own
 *   password. The password itself never appears anywhere.
 * - An existing non-admin account with that email is never promoted, because
 *   anyone can register an address before the owner deploys.
 */
#[Signature('pal:bootstrap-admin {--email= : Defaults to the ADMIN_EMAIL environment variable}')]
#[Description('Create the first administrator from ADMIN_EMAIL and print a one-time password setup link')]
class BootstrapAdmin extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $email = Str::lower(trim((string) ($this->option('email') ?: config('auth.bootstrap_admin_email', ''))));

        if ($email === '') {
            $this->line('ADMIN_EMAIL is not set; no administrator bootstrap.');

            return self::SUCCESS;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('ADMIN_EMAIL is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user && ! $user->isAdmin()) {
            $this->error("{$email} is registered as a {$user->role}, not an administrator. It was not changed. Use another ADMIN_EMAIL, or promote it with pal:create-admin from a trusted shell.");

            return self::FAILURE;
        }

        if (! $user) {
            $user = new User(['name' => 'PAL Administrator', 'email' => $email, 'password' => Str::random(64)]);
            $user->forceFill(['role' => 'admin', 'email_verified_at' => now()])->save();
            $audit->record('admin.created', "Created administrator {$email} from ADMIN_EMAIL at deploy.", $user);
            $this->info("Administrator {$email} created.");
        }

        if ($user->last_login_at !== null) {
            $this->line("Administrator {$email} has already signed in; no setup link printed.");

            return self::SUCCESS;
        }

        $token = Password::broker()->createToken($user);
        $url = rtrim((string) config('app.url'), '/').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);

        $this->newLine();
        $this->line('==================== PAL MARKET ADMIN SETUP ====================');
        $this->line('Open this link within 60 minutes to choose the admin password.');
        $this->line('It works once. Keep this log private.');
        $this->line($url);
        $this->line('Then sign in at /login and turn on two-step sign-in when asked.');
        $this->line('================================================================');
        $this->newLine();

        return self::SUCCESS;
    }
}
