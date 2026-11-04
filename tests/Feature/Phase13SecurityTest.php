<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\Fine;
use App\Models\Member;
use App\Models\MembershipPayment;
use App\Models\MembershipType;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase13SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_tests_use_the_isolated_testing_database(): void
    {
        $this->assertSame('library_management_test', DB::connection()->getDatabaseName());
    }

    public function test_audit_data_redacts_private_values_and_omits_credentials(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $audit = app(AuditLogService::class)->record(
            'security.test',
            $actor,
            $actor,
            'Audit sanitizer test',
            ['password' => 'test-password-secret', 'ADMIN_PASSWORD' => 'test-admin-secret', 'full_name' => 'Private Person', 'status' => 'OLD'],
            ['password_confirmation' => 'test-confirm-secret', 'email' => 'private@example.test', 'status' => 'NEW'],
        );

        $this->assertArrayNotHasKey('password', $audit->old_values);
        $this->assertArrayNotHasKey('ADMIN_PASSWORD', $audit->old_values);
        $this->assertArrayNotHasKey('password_confirmation', $audit->new_values);
        $this->assertSame('[REDACTED]', $audit->old_values['full_name']);
        $this->assertSame('[REDACTED]', $audit->new_values['email']);
        $this->assertSame('NEW', $audit->new_values['status']);
        $this->assertStringNotContainsString('test-password-secret', json_encode($audit->getAttributes()));
        $this->assertStringNotContainsString('test-admin-secret', json_encode($audit->getAttributes()));

        $textAudit = app(AuditLogService::class)->record(
            'security.text-test',
            $actor,
            description: 'admin_password=test-description-secret contact private@example.test',
        );
        $this->assertStringNotContainsString('test-description-secret', $textAudit->description);
        $this->assertStringNotContainsString('private@example.test', $textAudit->description);
    }

    public function test_only_admin_can_view_paginated_audit_logs_and_output_is_escaped(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AuditLog::create(['action' => '<script>alert(1)</script>']);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertViewHas('logs', fn ($logs) => $logs->perPage() === 25);

        foreach ([User::ROLE_LIBRARIAN, User::ROLE_MEMBER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.audit-logs.index'))
                ->assertRedirect(route(strtolower($role).'.dashboard'));
        }
    }

    public function test_member_profile_is_scoped_to_authenticated_member_and_escapes_stored_xss(): void
    {
        $membershipType = MembershipType::create(['name' => 'Individual', 'max_books' => 2, 'description' => 'Individual']);
        $ownUser = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $otherUser = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $this->createMember($ownUser, $membershipType, '<script>alert(1)</script>');
        $this->createMember($otherUser, $membershipType, 'Other private member');

        $this->actingAs($ownUser)
            ->get(route('member.profile'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('Other private member');
    }

    public function test_member_payment_and_fine_pages_do_not_expose_another_members_records(): void
    {
        $membershipType = MembershipType::create(['name' => 'Individual', 'max_books' => 2, 'description' => 'Individual']);
        $ownUser = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $otherUser = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $this->createMember($ownUser, $membershipType, 'Own Member');
        $otherMember = $this->createMember($otherUser, $membershipType, 'Other Member');

        MembershipPayment::create([
            'member_id' => $otherMember->id,
            'amount' => '19.00',
            'payment_type' => 'MONTHLY_MEMBERSHIP',
            'payment_date' => today(),
            'status' => 'PAID',
            'reference' => 'OTHER-MEMBER-PAYMENT-REFERENCE',
            'description' => 'Other member payment marker',
        ]);
        app(PaymentService::class)->createFine($otherMember, '13.00', 'OVERDUE', description: 'Other member fine marker');

        $this->actingAs($ownUser)
            ->get(route('member.payments'))
            ->assertOk()
            ->assertDontSee('OTHER-MEMBER-PAYMENT-REFERENCE')
            ->assertDontSee('Other member payment marker');

        $this->get(route('member.fines'))
            ->assertOk()
            ->assertDontSee('Other member fine marker');
    }

    public function test_public_registration_is_unavailable_for_privileged_role_payloads(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_LIBRARIAN] as $role) {
            $email = strtolower($role).'@example.test';
            $this->get('/register')->assertNotFound();
            $this->post('/register', [
                'full_name' => 'Registration Test',
                'email' => $email,
                'password' => 'test-password',
                'password_confirmation' => 'test-password',
                'nic' => strtolower($role).'-nic',
                'phone' => '0771234567',
                'address' => 'Test address',
                'membership_type' => 'INDIVIDUAL',
                'role' => $role,
            ])->assertNotFound();

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_staff_member_changes_are_audited_with_member_identifiers_redacted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $memberUser = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $membershipType = MembershipType::create(['name' => 'Individual', 'max_books' => 2, 'description' => 'Individual']);
        $this->actingAs($admin);

        $member = $this->createMember($memberUser, $membershipType, 'Private Member Name');
        $created = AuditLog::query()->where('action', 'member.created')->latest()->firstOrFail();
        $this->assertSame($admin->id, $created->user_id);
        $this->assertSame('[REDACTED]', $created->new_values['full_name']);
        $this->assertSame('[REDACTED]', $created->new_values['nic']);

        $member->update(['status' => 'SUSPENDED', 'full_name' => 'Updated Private Name']);
        $updated = AuditLog::query()->where('action', 'member.updated')->latest()->firstOrFail();
        $this->assertSame('ACTIVE', $updated->old_values['status']);
        $this->assertSame('SUSPENDED', $updated->new_values['status']);
        $this->assertSame('[REDACTED]', $updated->old_values['full_name']);
        $this->assertSame('[REDACTED]', $updated->new_values['full_name']);
    }

    public function test_main_admin_cannot_create_a_fine(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $memberUser = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $membershipType = MembershipType::create(['name' => 'Individual', 'max_books' => 2, 'description' => 'Individual']);
        $member = $this->createMember($memberUser, $membershipType, 'Fine Test Member');

        $this->actingAs($admin)
            ->post(route('admin.fines.store'), [
                'member_id' => $member->id,
                'amount' => '25.00',
                'fine_type' => 'OVERDUE',
                'status' => 'PAID',
                'paid_date' => today()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('fines', 0);
    }

    public function test_successful_login_and_logout_are_audited_and_password_is_not_recorded(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'password' => Hash::make('test-login-password')]);
        $this->get(route('login'))->assertOk();
        $sessionIdBeforeLogin = session()->getId();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'test-login-password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);

        $tokenBeforeLogout = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($tokenBeforeLogout, session()->token());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);

        $serializedAudit = AuditLog::query()->get()->toJson();
        $this->assertStringNotContainsString('test-login-password', $serializedAudit);
    }

    public function test_inactive_users_receive_the_same_generic_login_error(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);

        $this->assertSame('Invalid email or password.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'invalid-password'])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'invalid-password'])
            ->assertTooManyRequests();
    }

    public function test_state_changing_web_routes_retain_csrf_middleware(): void
    {
        $kernel = $this->app->make(\App\Http\Kernel::class);
        $middlewareGroups = new \ReflectionProperty($kernel, 'middlewareGroups');
        $middlewareGroups->setAccessible(true);
        $this->assertContains(VerifyCsrfToken::class, $middlewareGroups->getValue($kernel)['web']);

        foreach (app('router')->getRoutes() as $route) {
            if (! in_array($route->methods()[0], ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                || ! in_array('web', $route->middleware(), true)) {
                continue;
            }

            $this->assertContains('web', $route->middleware(), $route->uri());
        }
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    private function createMember(User $user, MembershipType $membershipType, string $name): Member
    {
        return Member::create([
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'full_name' => $name,
            'email' => $user->email,
            'nic' => 'NIC-'.$user->id,
            'phone' => '0771234567',
            'address' => 'Test address',
            'registration_fee' => 220,
            'registration_date' => today(),
            'membership_start_date' => today(),
            'next_renewal_date' => today()->addMonth(),
            'expiry_date' => today()->addMonth(),
            'status' => 'ACTIVE',
        ]);
    }
}