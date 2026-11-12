<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'ride:create-admin {--name=} {--email=} {--phone=}';

    protected $description = 'Create an administrator account (the only way to create admins; the password is prompted, never stored in files).';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: $this->ask('Full name'),
            'email' => $this->option('email') ?: $this->ask('Email'),
            'phone' => $this->option('phone') ?: $this->ask('Phone (e.g. 03001234567)'),
            'password' => $this->secret('Password (min 8 chars, letters + numbers)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($data + ['user_type' => User::TYPE_ADMIN, 'is_verified' => true, 'status' => 'active']);

        $this->info("Admin #{$user->id} ({$user->email}) created.");

        return self::SUCCESS;
    }
}
