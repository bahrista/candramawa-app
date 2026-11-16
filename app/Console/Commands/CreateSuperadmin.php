<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-superadmin {--name= : Full name} {--username= : Login username}')]
#[Description('Create a Superadmin account (password is asked via a hidden prompt)')]
class CreateSuperadmin extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $username = mb_strtolower((string) ($this->option('username') ?: $this->ask('Username')));
        $password = $this->secret('Password (min 8 characters)');
        $confirm = $this->secret('Confirm password');

        $validator = Validator::make(
            compact('name', 'username', 'password') + ['password_confirmation' => $confirm],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._]+$/', 'unique:users,username'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'username' => $username, 'password' => $password]);
        $user->role = User::ROLE_SUPERADMIN;
        $user->is_active = true;
        $user->save();

        $this->info("Superadmin '{$user->username}' created.");

        return self::SUCCESS;
    }
}
