<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@library.com');
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($password === '') {
            throw new RuntimeException('Set ADMIN_PASSWORD in the environment before provisioning the admin user.');
        }

        $admin = User::query()->where('email', $email)->first();

        if ($admin && ! $admin->hasRole(User::ROLE_ADMIN)) {
            throw new RuntimeException('The configured admin email belongs to a non-admin user; refusing to change its role.');
        }

        if (! $admin && User::query()->where('role', User::ROLE_ADMIN)->exists()) {
            throw new RuntimeException('An admin user already exists; refusing to create another admin account.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Library Administrator',
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]
        );
    }
}