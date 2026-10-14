<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('pal:create-admin {email} {--name=PAL Administrator}')]
#[Description('Create an administrator account, or promote an existing account to administrator')]
class CreateAdmin extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $email = strtolower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();

        if ($user) {
            if (! $this->confirm("{$email} already exists as a {$user->role}. Promote it to administrator?")) {
                return self::FAILURE;
            }
            $user->forceFill(['role' => 'admin'])->save();
            $audit->record('admin.created', "Promoted {$email} to administrator from the command line.", $user);
            $this->info("{$email} is now an administrator.");

            return self::SUCCESS;
        }

        $password = $this->secret('Password (min. 10 characters)');
        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(10)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
        $user->forceFill(['role' => 'admin', 'email_verified_at' => now()])->save();

        $audit->record('admin.created', "Created administrator {$email} from the command line.", $user);
        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
