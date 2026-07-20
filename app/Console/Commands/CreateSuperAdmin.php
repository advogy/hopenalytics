<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'make:superadmin';

    protected $description = 'Bootstrap the first superadmin account (bypasses OTP verification)';

    public function handle(): int
    {
        $name = $this->ask('Nama');

        $email = $this->ask('Email');
        $emailError = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:users,email']])
            ->errors()->first('email');

        if ($emailError) {
            $this->error($emailError);

            return self::FAILURE;
        }

        $password = $this->secret('Kata sandi (minimal 8 karakter)');

        if (strlen($password) < 8) {
            $this->error('Kata sandi minimal 8 karakter.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Superadmin \"{$user->name}\" ({$user->email}) berhasil dibuat.");

        return self::SUCCESS;
    }
}
