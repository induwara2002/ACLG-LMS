<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\User;
use App\Services\MembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    private function membershipType(string $name = 'Children', int $limit = 2): MembershipType
    {
        return MembershipType::firstOrCreate(['name' => $name], ['max_books' => $limit, 'description' => $name . ' membership']);
    }

    private function member(string $name = 'Member One', string $email = 'member@example.com'): Member
    {
        $type = $this->membershipType();
        $date = Carbon::today();
        $user = User::factory()->create(['name' => $name, 'email' => $email, 'role' => 'MEMBER', 'is_active' => true]);

        return Member::create([
            'user_id' => $user->id,
            'membership_type_id' => $type->id,
            'full_name' => $name,
            'email' => $email,
            'nic' => 'NIC-' . $user->id,
            'phone' => '0770000000',
            'address' => 'Colombo',
            'registration_fee' => 220,
            'registration_date' => $date,
            'membership_start_date' => $date,
            'next_renewal_date' => $date->copy()->addMonth(),
            'expiry_date' => $date->copy()->addMonth(),
            'status' => 'ACTIVE',
        ]);
    }

    public function test_registration_creates_one_paid_registration_payment(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'PAYREG', 'name' => 'Payment Registration Sabha']);
        $admin = User::factory()->create([
            'role' => User::ROLE_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $admin->id,
            'is_active' => true,
        ]);

        foreach ([
            'registration_fee' => '220.00', 'monthly_membership_fee' => '120.00',
            'late_renewal_fee' => '60.00', 'late_renewal_penalty' => '0.00',
            'overdue_fine_amount' => '2.50', 'membership_duration_months' => '1',
            'individual_borrowing_limit' => '2', 'family_borrowing_limit' => '3',
            'issue_period_days' => '14', 'maximum_renewals' => '2',
            'currency' => 'LKR', 'timezone' => 'Asia/Colombo',
        ] as $key => $value) {
            PradeshiyaSabhaSetting::create([
                'pradeshiya_sabha_id' => $sabha->id,
                'key' => $key,
                'value' => $value,
            ]);
        }

        $this->actingAs($admin)->post(route('admin.members.store'), [
            'full_name' => 'New Member',
            'email' => 'new@example.com',
            'password' => 'secret-member-password',
            'password_confirmation' => 'secret-member-password',
            'nic' => 'NIC-NEW',
            'phone' => '0771111111',
            'address' => 'Kandy',
            'membership_type_id' => $this->membershipType()->id,
        ])->assertRedirect();

        $member = Member::where('email', 'new@example.com')->firstOrFail();
        $this->assertNotNull($member->created_at);
        $this->assertSame(today()->toDateString(), $member->registration_date->toDateString());
        $this->assertDatabaseHas('membership_payments', [
            'member_id' => $member->id, 'payment_type' => 'REGISTRATION', 'amount' => 220, 'status' => 'PAID',
        ]);
        $this->assertSame(1, $member->membershipPayments()->where('payment_type', 'REGISTRATION')->count());
        $this->assertTrue(Hash::check('secret-member-password', $member->user->password));
    }

    public function test_normal_and_late_renewal_use_fixed_amounts_and_dates(): void
    {
        $member = $this->member();
        $service = app(MembershipService::class);

        $service->renew($member);
        $this->assertDatabaseHas('membership_payments', ['member_id' => $member->id, 'payment_type' => 'MONTHLY_MEMBERSHIP', 'amount' => 120]);

        $member->update(['expiry_date' => Carbon::yesterday(), 'next_renewal_date' => Carbon::yesterday(), 'status' => 'ACTIVE']);
        $service->renew($member->fresh());
        $this->assertDatabaseHas('membership_payments', ['member_id' => $member->id, 'payment_type' => 'LATE_RENEWAL', 'amount' => 180]);
        $this->assertSame('ACTIVE', $member->fresh()->status);
    }

    public function test_suspension_is_not_overridden_by_effective_status(): void
    {
        $member = $this->member();
        $member->update(['status' => 'SUSPENDED', 'expiry_date' => Carbon::yesterday()]);

        $this->assertSame('SUSPENDED', $member->fresh()->effectiveStatus());
    }

    public function test_member_can_only_view_own_membership(): void
    {
        $member = $this->member();
        $other = $this->member('Member Two', 'two@example.com');

        $this->actingAs($member->user)->get('/member/profile')->assertOk()->assertSee($member->member_id)->assertDontSee($other->member_id);
        $this->actingAs($member->user)->get('/admin/members')->assertRedirect('/member/dashboard');
    }

    public function test_staff_can_search_and_filter_members(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $member = $this->member('Searchable Name', 'search@example.com');

        $this->actingAs($admin)->get('/admin/members?search=Searchable')->assertOk()->assertSee($member->member_id);
        $this->actingAs($admin)->get('/admin/members?status=ACTIVE')->assertOk()->assertSee($member->member_id);
    }
}
