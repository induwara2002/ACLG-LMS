<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TestAdminSeeder extends Seeder
{
    private const EMAIL = 'testadmin@library.com';
    private const PASSWORD = 'TestAdmin@12345';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('The temporary test administrator may only be provisioned locally.');
        }

        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Test Administrator',
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]
        );

        $user->refresh();

        if (
            $user->name !== 'Test Administrator'
            || ! $user->hasRole(User::ROLE_ADMIN)
            || ! $user->is_active
            || ! Hash::check(self::PASSWORD, $user->password)
        ) {
            throw new RuntimeException('Temporary test administrator verification failed.');
        }

        $this->command?->info('Temporary test administrator verified; password hash is valid.');
    }
}
