<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private string $adminPassword;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminPassword = bin2hex(random_bytes(24));
        putenv('ADMIN_EMAIL=admin@library.com');
        putenv('ADMIN_PASSWORD='.$this->adminPassword);
        $_ENV['ADMIN_EMAIL'] = 'admin@library.com';
        $_ENV['ADMIN_PASSWORD'] = $this->adminPassword;
        $_SERVER['ADMIN_EMAIL'] = 'admin@library.com';
        $_SERVER['ADMIN_PASSWORD'] = $this->adminPassword;
    }

    public function test_admin_user_seed_is_idempotent_and_uses_hashed_password(): void
    {
        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@library.com',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $admin = User::query()->where('email', 'admin@library.com')->firstOrFail();
        $this->assertSame(1, User::query()->where('email', 'admin@library.com')->count());
        $this->assertEquals(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue((bool) $admin->is_active);
        $this->assertTrue(Hash::check($this->adminPassword, $admin->password));

        $this->post('/login', [
            'email' => 'admin@library.com',
            'password' => $this->adminPassword,
        ])->assertRedirect('/admin/dashboard');

        $this->get('/admin/dashboard')->assertOk();

        foreach ([
            '/admin/books',
            '/admin/authors',
            '/admin/publishers',
            '/admin/categories',
            '/admin/members',
            '/admin/borrowings',
            '/admin/payments',
            '/admin/fines',
            '/admin/lost-damaged',
            '/admin/reports',
        ] as $adminPath) {
                $response = $this->get($adminPath);
                $this->assertSame(200, $response->status(), "Admin route {$adminPath} returned {$response->status()}.");
        }

        $this->post('/logout')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])->assertOk();

        $this->assertSame(1, User::query()->where('email', 'admin@library.com')->count());
        $this->assertSame(1, User::query()->where('role', User::ROLE_ADMIN)->count());

        $this->from('/login')->post('/login', [
            'email' => 'admin@library.com',
            'password' => bin2hex(random_bytes(24)),
        ])->assertRedirect('/login')->assertSessionHasErrors(['email']);
        $this->assertSame('Invalid email or password.', session('errors')->first('email'));
    }

    public function test_non_admin_roles_and_public_registration_cannot_gain_admin_access(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');

        $member = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
        ]);

        $this->actingAs($member)
            ->get('/admin/dashboard')
            ->assertRedirect('/member/dashboard');

        $librarian = User::factory()->create([
            'role' => User::ROLE_LIBRARIAN,
            'is_active' => true,
        ]);

        $this->actingAs($librarian)
            ->get('/admin/authors')
            ->assertRedirect('/librarian/dashboard');

            $this->post('/logout')->assertRedirect('/login');

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'email' => 'privileged@example.com',
            'role' => User::ROLE_ADMIN,
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'privileged@example.com']);
    }
}
