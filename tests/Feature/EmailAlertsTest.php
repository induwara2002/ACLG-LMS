<?php

namespace Tests\Feature;

use App\Mail\LibraryAlertMail;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\Publisher;
use App\Models\User;
use App\Services\BorrowingService;
use App\Services\MembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_member_registration_sends_welcome_email_without_sensitive_details(): void
    {
        Mail::fake();
        [$admin] = $this->registrationAdmin();

        $this->actingAs($admin)->post(route('admin.members.store'), $this->registrationData())
            ->assertRedirect();

        $member = Member::where('email', 'new-member@example.com')->firstOrFail();
        Mail::assertSent(LibraryAlertMail::class, function (LibraryAlertMail $mail) use ($member) {
            return $mail->alertType === 'member_registered'
                && $mail->hasTo('new-member@example.com')
                && $mail->memberName === $member->full_name
                && $mail->details['Membership type'] === 'Children'
                && ! array_key_exists('Password', $mail->details);
        });
    }

    public function test_invalid_member_registration_does_not_send_email(): void
    {
        Mail::fake();
        [$admin] = $this->registrationAdmin();
        $data = $this->registrationData();
        unset($data['email']);

        $this->actingAs($admin)->from(route('admin.members.create'))
            ->post(route('admin.members.store'), $data)
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'new-member@example.com']);
    }

    public function test_rolled_back_registration_does_not_send_email(): void
    {
        Mail::fake();
        [$admin] = $this->registrationAdmin();
        $this->mock(MembershipService::class, function ($mock) {
            $mock->shouldReceive('membershipDurationMonths')->once()->andReturn(1);
            $mock->shouldReceive('registerPayment')->once()->andThrow(new RuntimeException('payment failed'));
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('admin.members.store'), $this->registrationData());
            $this->fail('The registration transaction should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('payment failed', $exception->getMessage());
        }

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'new-member@example.com']);
        $this->assertDatabaseMissing('members', ['email' => 'new-member@example.com']);
    }

    public function test_successful_book_issue_sends_the_calculated_due_date(): void
    {
        Mail::fake();
        $member = $this->member();
        $book = $this->book();
        $librarian = User::factory()->create(['role' => User::ROLE_LIBRARIAN, 'is_active' => true]);

        $this->actingAs($librarian)->post('/librarian/borrowings', [
            'member_id' => $member->id,
            'book_id' => $book->id,
        ])->assertRedirect('/librarian/borrowings');

        $borrowing = Borrowing::firstOrFail();
        Mail::assertSent(LibraryAlertMail::class, function (LibraryAlertMail $mail) use ($borrowing, $book, $member) {
            return $mail->alertType === 'book_issued'
                && $mail->hasTo($member->email)
                && $mail->details['Book'] === $book->title
                && $mail->details['Issue date'] === Carbon::today()->format('F j, Y')
                && $mail->details['Return due date'] === $borrowing->due_date->format('F j, Y');
        });
    }

    public function test_failed_book_issue_does_not_send_email(): void
    {
        Mail::fake();
        $member = $this->member();
        $member->update(['expiry_date' => Carbon::yesterday()]);
        $book = $this->book();
        $librarian = User::factory()->create(['role' => User::ROLE_LIBRARIAN, 'is_active' => true]);

        $this->actingAs($librarian)->from('/librarian/borrowings/create')->post('/librarian/borrowings', [
            'member_id' => $member->id,
            'book_id' => $book->id,
        ])->assertSessionHasErrors('issue');

        Mail::assertNothingSent();
        $this->assertSame(0, Borrowing::count());
    }

    public function test_cross_sabha_book_issue_is_rejected_without_sending_email(): void
    {
        Mail::fake();
        $firstSabha = PradeshiyaSabha::create([
            'code' => 'FIRST'.random_int(100, 999),
            'name' => 'First Email Test Sabha',
        ]);
        $secondSabha = PradeshiyaSabha::create([
            'code' => 'SECOND'.random_int(100, 999),
            'name' => 'Second Email Test Sabha',
        ]);
        $member = $this->member();
        $member->update(['pradeshiya_sabha_id' => $firstSabha->id]);
        $member->user()->update(['pradeshiya_sabha_id' => $firstSabha->id]);
        $book = $this->book();
        $book->update(['pradeshiya_sabha_id' => $secondSabha->id]);
        $book->copies()->update(['pradeshiya_sabha_id' => $secondSabha->id]);

        try {
            app(BorrowingService::class)->issue($member, $book);
            $this->fail('A book from another Sabha must not be issued.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('same Pradeshiya Sabha', $exception->getMessage());
        }

        Mail::assertNothingSent();
        $this->assertSame(0, Borrowing::count());
    }

    public function test_due_date_reminders_only_send_for_eligible_loans_and_are_deduplicated(): void
    {
        Mail::fake();
        $member = $this->member();
        $book = $this->book();
        $dueDate = Carbon::today()->addDays(2);
        $eligible = $this->borrowing($member, $book, $dueDate);
        $this->borrowing($member, $book, $dueDate, 'RETURNED', Carbon::today());
        $this->borrowing($member, $book, $dueDate, 'CANCELLED');
        $this->borrowing($member, $book, $dueDate, 'LOST');
        $this->borrowing($member, $book, Carbon::today()->addDays(3));

        $this->artisan('library:remind-loans')->assertExitCode(0);
        $this->artisan('library:remind-loans')->assertExitCode(0);

        $this->assertSame(1, Mail::sent(LibraryAlertMail::class)->count());
        Mail::assertSent(LibraryAlertMail::class, fn (LibraryAlertMail $mail) => $mail->alertType === 'due_date_reminder'
            && $mail->details['Return due date'] === $dueDate->format('F j, Y'));
        $this->assertDatabaseHas('email_alert_deliveries', [
            'alert_type' => 'due_date_reminder',
            'alertable_type' => 'borrowing',
            'alertable_id' => $eligible->id,
            'period_date' => $dueDate->toDateString(),
        ]);
        $this->assertSame(1, DB::table('email_alert_deliveries')->where('alert_type', 'due_date_reminder')->count());
    }

    public function test_due_date_reminder_safely_skips_missing_email(): void
    {
        Mail::fake();
        $member = $this->member();
        $member->update(['email' => null]);
        $this->borrowing($member, $this->book(), Carbon::today()->addDays(2));

        $this->artisan('library:remind-loans')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('email_alert_deliveries', ['alert_type' => 'due_date_reminder']);
    }

    public function test_membership_expiry_reminders_only_send_once_for_active_unexpired_memberships(): void
    {
        Mail::fake();
        $expiryDate = Carbon::today()->addDays(7);
        $eligible = $this->member('Active Member', 'active-member@example.com', 'ACTIVE', $expiryDate);
        $this->member('Suspended Member', 'suspended-member@example.com', 'SUSPENDED', $expiryDate);
        $this->member('Expired Member', 'expired-member@example.com', 'EXPIRED', $expiryDate);
        $this->member('Past Expiry', 'past-expiry@example.com', 'ACTIVE', Carbon::yesterday());
        $noEmail = $this->member('No Email', null, 'ACTIVE', $expiryDate);
        $noEmail->update(['email' => null]);

        $this->artisan('library:remind-membership-expiry')->assertExitCode(0);
        $this->artisan('library:remind-membership-expiry')->assertExitCode(0);

        $this->assertSame(1, Mail::sent(LibraryAlertMail::class)->count());
        Mail::assertSent(LibraryAlertMail::class, fn (LibraryAlertMail $mail) => $mail->alertType === 'membership_expiry_reminder'
            && $mail->hasTo('active-member@example.com')
            && $mail->details['Membership expires'] === $expiryDate->format('F j, Y'));
        $this->assertDatabaseHas('email_alert_deliveries', [
            'alert_type' => 'membership_expiry_reminder',
            'alertable_type' => 'member',
            'alertable_id' => $eligible->id,
            'period_date' => $expiryDate->toDateString(),
        ]);
        $this->assertSame(1, DB::table('email_alert_deliveries')->where('alert_type', 'membership_expiry_reminder')->count());
    }

    public function test_smtp_failure_is_logged_without_rolling_back_registration(): void
    {
        [$admin] = $this->registrationAdmin();
        $pendingMail = Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(Mockery::type(LibraryAlertMail::class))
            ->andThrow(new RuntimeException('transport failure'));
        Mail::shouldReceive('to')->once()->with('new-member@example.com')->andReturn($pendingMail);
        Log::shouldReceive('error')->once()->with(
            'Library email alert could not be sent.',
            Mockery::on(fn (array $context) => $context['alert_type'] === 'member_registered'
                && $context['exception'] === RuntimeException::class)
        );

        $this->actingAs($admin)->post(route('admin.members.store'), $this->registrationData())
            ->assertRedirect();

        $this->assertDatabaseHas('members', ['email' => 'new-member@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'new-member@example.com']);
    }

    private function registrationAdmin(): array
    {
        $sabha = PradeshiyaSabha::create(['code' => 'MAIL'.random_int(100, 999), 'name' => 'Email Test Sabha']);
        $admin = User::factory()->create([
            'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $admin->id,
            'is_active' => true,
        ]);

        foreach ([
            'registration_fee' => '220.00',
            'monthly_membership_fee' => '120.00',
            'late_renewal_fee' => '60.00',
            'late_renewal_penalty' => '0.00',
            'overdue_fine_amount' => '2.50',
            'membership_duration_months' => '1',
            'individual_borrowing_limit' => '2',
            'family_borrowing_limit' => '3',
            'issue_period_days' => '14',
            'maximum_renewals' => '2',
            'currency' => 'LKR',
            'timezone' => 'Asia/Colombo',
        ] as $key => $value) {
            PradeshiyaSabhaSetting::create([
                'pradeshiya_sabha_id' => $sabha->id,
                'key' => $key,
                'value' => $value,
            ]);
        }

        MembershipType::firstOrCreate(
            ['name' => 'Children'],
            ['max_books' => 2, 'description' => 'Children membership'],
        );

        return [$admin, $sabha];
    }

    private function registrationData(): array
    {
        return [
            'full_name' => 'New Member',
            'email' => 'new-member@example.com',
            'password' => 'secret-member-password',
            'password_confirmation' => 'secret-member-password',
            'nic' => 'NIC-NEW-MAIL',
            'phone' => '0771111111',
            'address' => 'Kandy',
            'membership_type_id' => MembershipType::where('name', 'Children')->value('id'),
        ];
    }

    private function member(
        string $name = 'Reminder Member',
        ?string $email = null,
        string $status = 'ACTIVE',
        ?Carbon $expiryDate = null,
    ): Member {
        $type = MembershipType::firstOrCreate(
            ['name' => 'Individual'],
            ['max_books' => 2, 'description' => 'Individual membership'],
        );
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email ?? 'member-'.uniqid().'@example.com',
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
        ]);

        return Member::create([
            'user_id' => $user->id,
            'membership_type_id' => $type->id,
            'full_name' => $name,
            'email' => $email ?? 'member-'.$user->id.'@example.com',
            'nic' => 'NIC-'.$user->id,
            'phone' => '0770000000',
            'address' => 'Colombo',
            'registration_fee' => 220,
            'registration_date' => Carbon::today(),
            'membership_start_date' => Carbon::today(),
            'next_renewal_date' => $expiryDate ?? Carbon::today()->addMonth(),
            'expiry_date' => $expiryDate ?? Carbon::today()->addMonth(),
            'status' => $status,
        ]);
    }

    private function book(): Book
    {
        $author = Author::create(['name' => 'Email Test Author']);
        $publisher = Publisher::create(['name' => 'Email Test Publisher']);
        $category = Category::firstOrCreate(['code' => 'MAIL'], ['name' => 'Email Test']);
        $book = Book::create([
            'book_id' => 'MAIL-'.uniqid(),
            'title' => 'Email Alert Book',
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '600.1',
            'status' => 'available',
        ]);
        BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'MAIL-COPY-'.uniqid(),
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => Carbon::today(),
        ]);

        return $book;
    }

    private function borrowing(
        Member $member,
        Book $book,
        Carbon $dueDate,
        string $status = 'ISSUED',
        ?Carbon $returnDate = null,
    ): Borrowing {
        $copy = $book->copies()->first();

        return Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => $copy->id,
            'issue_date' => Carbon::today(),
            'due_date' => $dueDate,
            'return_date' => $returnDate,
            'status' => $status,
            'renewal_count' => 0,
        ]);
    }
}
