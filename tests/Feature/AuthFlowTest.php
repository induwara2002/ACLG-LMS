<?php

namespace Tests\Feature;

use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_and_home_compatibility_route(): void
    {
        $this->get('/')->assertOk();
        $this->get('/home')->assertRedirect('/');
    }

    public function test_authenticated_users_are_redirected_to_their_role_dashboard(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'AUTH', 'name' => 'Authentication Sabha']);
        $sabhaAdmin = User::factory()->create([
            'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $sabhaAdmin->id,
            'is_active' => true,
        ]);
        $canonicalSabhaAdmin = User::factory()->create([
            'role' => User::ROLE_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $canonicalSabha = PradeshiyaSabha::create(['code' => 'AUTH2', 'name' => 'Authentication Sabha Two']);
        $canonicalSabhaAdmin->update(['pradeshiya_sabha_id' => $canonicalSabha->id]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $canonicalSabha->id,
            'user_id' => $canonicalSabhaAdmin->id,
            'is_active' => true,
        ]);

        $roles = [
            [User::ROLE_MAIN_ADMIN, '/admin/dashboard'],
            [User::ROLE_SUPER_ADMIN, '/admin/dashboard'],
            [$sabhaAdmin, '/admin/dashboard'],
            [$canonicalSabhaAdmin, '/admin/dashboard'],
            [User::ROLE_LIBRARIAN, '/librarian/dashboard'],
            [User::ROLE_MEMBER, '/member/dashboard'],
        ];

        foreach ($roles as [$role, $dashboard]) {
            $user = $role instanceof User
                ? $role
                : User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($user)->get('/login')->assertRedirect($dashboard);
        }
    }

    public function test_public_member_registration_creates_a_pending_account_in_the_selected_sabha(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'REG', 'name' => 'Registration Sabha', 'is_active' => true]);
        $inactiveSabha = PradeshiyaSabha::create(['code' => 'REGX', 'name' => 'Inactive Registration Sabha', 'is_active' => false]);
        $membershipType = MembershipType::query()->firstOrCreate(
            ['name' => 'Children'],
            ['max_books' => 3, 'description' => 'Children membership']
        );

        $this->get('/register')->assertOk()->assertSee($sabha->name)->assertDontSee($inactiveSabha->name);
        $this->from('/register')->post('/register', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'not-a-public-account-password',
            'password_confirmation' => 'not-a-public-account-password',
            'nic' => '123456789V',
            'phone' => '0771234567',
            'address' => 'Colombo 03',
            'membership_type_id' => $membershipType->id,
            'pradeshiya_sabha_id' => $inactiveSabha->id,
        ])->assertSessionHasErrors('pradeshiya_sabha_id');

        $this->post('/register', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'not-a-public-account-password',
            'password_confirmation' => 'not-a-public-account-password',
            'nic' => '123456789V',
            'phone' => '0771234567',
            'address' => 'Colombo 03',
            'membership_type_id' => $membershipType->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'role' => User::ROLE_MAIN_ADMIN,
            'status' => 'ACTIVE',
        ])->assertSessionHasErrors(['role', 'status']);
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);

        $this->post('/register', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'not-a-public-account-password',
            'password_confirmation' => 'not-a-public-account-password',
            'nic' => '123456789V',
            'phone' => '0771234567',
            'address' => 'Colombo 03',
            'membership_type_id' => $membershipType->id,
            'pradeshiya_sabha_id' => $sabha->id,
        ])->assertRedirect(route('register.submitted'))->assertSessionHas('registration_submitted', true);

        $member = Member::query()->where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('PENDING', $member->status);
        $this->assertDatabaseHas('members', [
            'email' => 'jane@example.com',
            'status' => 'PENDING',
        ]);
        $this->assertSame($sabha->id, (int) $member->pradeshiya_sabha_id);
        $this->assertSame($sabha->id, (int) $member->user->pradeshiya_sabha_id);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'role' => User::ROLE_MEMBER]);

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'not-a-public-account-password'])
            ->assertRedirect(route('member.dashboard'));
        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Membership Pending Approval')
            ->assertSee($sabha->name)
            ->assertSee(today()->format('Y-m-d'));
    }

    public function test_invalid_login_shows_generic_error_message(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertEquals('Invalid email or password.', session('errors')->first('email'));
    }

    public function test_member_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'MEMBER',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertRedirect('/member/dashboard');
    }

    public function test_admin_can_access_admin_dashboard_after_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@library.com',
            'role' => 'ADMIN',
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');

        Auth::forgetGuards();

        $this->get('/admin/dashboard')->assertOk();
    }
}
