<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Fine;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\Publisher;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFineFlowTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        $type = MembershipType::firstOrCreate(['name' => 'Individual'], ['max_books' => 2, 'description' => 'Individual']);
        $user = User::factory()->create(['role' => 'MEMBER', 'is_active' => true]);
        $today = Carbon::today();
        return Member::create(['user_id' => $user->id, 'membership_type_id' => $type->id, 'full_name' => $user->name, 'email' => $user->email, 'nic' => 'NIC-' . $user->id, 'phone' => '0770000000', 'address' => 'Colombo', 'registration_fee' => 220, 'registration_date' => $today, 'membership_start_date' => $today, 'next_renewal_date' => $today->copy()->addMonth(), 'expiry_date' => $today->copy()->addMonth(), 'status' => 'ACTIVE']);
    }

    public function test_registration_payment_is_configured_and_cannot_be_duplicated(): void
    {
        $member = $this->member();
        $service = app(PaymentService::class);
        $service->recordMembership($member, 'REGISTRATION');
        $this->assertDatabaseHas('membership_payments', ['member_id' => $member->id, 'payment_type' => 'REGISTRATION', 'amount' => 220, 'status' => 'PAID']);
        $this->expectExceptionMessage('Registration payment has already been recorded.');
        $service->recordMembership($member->fresh(), 'REGISTRATION');
    }

    public function test_monthly_and_late_membership_amounts_are_fixed(): void
    {
        $member = $this->member();
        $service = app(PaymentService::class);
        $service->recordMembership($member, 'MONTHLY_MEMBERSHIP');
        $service->recordMembership($member, 'LATE_RENEWAL');
        $this->assertDatabaseHas('membership_payments', ['member_id' => $member->id, 'payment_type' => 'MONTHLY_MEMBERSHIP', 'amount' => 120]);
        $this->assertDatabaseHas('membership_payments', ['member_id' => $member->id, 'payment_type' => 'LATE_RENEWAL', 'amount' => 180]);
    }

    public function test_money_arithmetic_uses_exact_decimal_strings(): void
    {
        $service = app(PaymentService::class);

        $this->assertSame('220.00', $service->registrationFee());
        $this->assertSame('120.00', $service->monthlyFee());
        $this->assertSame('180.00', $service->lateRenewalFee());
        $this->assertSame('340.00', PaymentService::fromCents(PaymentService::toCents('220.00') + PaymentService::toCents('120.00')));
        $this->assertSame('180.00', PaymentService::fromCents(PaymentService::toCents('120.00') + PaymentService::toCents('60.00')));
        $this->assertSame('5000.00', PaymentService::normalizeAmount('5000'));
    }

    public function test_fine_payment_updates_fine_and_outstanding_balance(): void
    {
        $member = $this->member();
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $fine = app(PaymentService::class)->createFine($member, '75.50', 'OVERDUE', null, 'Explicit staff-assigned overdue fine');
        $this->assertSame('75.50', app(PaymentService::class)->outstandingBalance($member));

        $this->actingAs($admin)->post("/librarian/fines/{$fine->id}/pay")->assertRedirect();
        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'status' => 'PAID']);
        $this->assertDatabaseHas('payments', ['fine_id' => $fine->id, 'payment_type' => 'OVERDUE_FINE', 'amount' => 75.50, 'status' => 'PAID']);
        $this->assertSame('0.00', app(PaymentService::class)->outstandingBalance($member->fresh()));
    }

    public function test_member_cannot_create_or_pay_fines_and_sees_only_own_history(): void
    {
        $member = $this->member();
        $other = $this->member();
        $fine = app(PaymentService::class)->createFine($other, '30.00', 'DAMAGE_COMPENSATION');
        Payment::create(['member_id' => $other->id, 'fine_id' => $fine->id, 'payment_type' => 'OVERDUE_FINE', 'amount' => 30, 'payment_method' => 'cash', 'payment_date' => Carbon::today(), 'reference_number' => 'OTHER-MEMBER-PAYMENT-REF', 'status' => 'PAID']);
        $this->actingAs($member->user)->post('/admin/fines', ['member_id' => $other->id, 'amount' => 100, 'fine_type' => 'OVERDUE'])->assertRedirect('/member/dashboard');
        $this->actingAs($member->user)->post("/admin/fines/{$fine->id}/pay")->assertRedirect('/member/dashboard');
        $this->actingAs($member->user)->get('/member/payments')->assertOk()->assertDontSee('OTHER-MEMBER-PAYMENT-REF');
    }

    public function test_waived_fine_is_excluded_from_balance_and_cannot_be_paid(): void
    {
        $member = $this->member();
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $fine = app(PaymentService::class)->createFine($member, '40.00', 'LOST_BOOK_COMPENSATION');
        $fine->update(['status' => 'WAIVED']);
        $this->assertSame('0.00', app(PaymentService::class)->outstandingBalance($member->fresh()));
        $this->actingAs($admin)->from('/librarian/fines')->post("/librarian/fines/{$fine->id}/pay")->assertSessionHasErrors('fine');
        $this->assertDatabaseCount('payments', 0);
    }
}
