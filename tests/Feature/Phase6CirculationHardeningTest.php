<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\DamageRecord;
use App\Models\Fine;
use App\Models\LostBook;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\Payment;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\Publisher;
use App\Models\User;
use App\Services\BorrowingService;
use App\Services\DamageRecordService;
use App\Services\LostBookService;
use App\Services\PaymentService;
use App\Services\PradeshiyaSabhaContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6CirculationHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_fine_for_the_same_borrowing_and_type_is_rejected(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $service = app(PaymentService::class);
        $service->createFine($member, '12.00', 'OVERDUE', $borrowing->id);

        $this->expectExceptionMessage('A fine of this type already exists for this borrowing.');
        $service->createFine($member, '12.00', 'OVERDUE', $borrowing->id);
    }

    public function test_duplicate_fine_submission_returns_a_validation_error_without_an_extra_record(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        app(PaymentService::class)->createFine($member, '12.00', 'OVERDUE', $borrowing->id);
        $librarian = User::factory()->create(['role' => User::ROLE_LIBRARIAN, 'is_active' => true]);

        $this->actingAs($librarian)
            ->from(route('librarian.fines.create'))
            ->post(route('librarian.fines.store'), [
                'member_id' => $member->id,
                'borrowing_id' => $borrowing->id,
                'amount' => '12.00',
                'fine_type' => 'OVERDUE',
            ])
            ->assertRedirect(route('librarian.fines.create'))
            ->assertSessionHasErrors('fine');

        $this->assertDatabaseCount('fines', 1);
    }

    public function test_issue_cannot_reuse_an_already_issued_physical_copy(): void
    {
        $member = $this->member();
        $book = $this->book();
        $service = app(BorrowingService::class);
        $borrowing = $service->issue($member, $book);

        try {
            $service->issue($member, $book);
            $this->fail('An issued physical copy must not be borrowed twice.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('No available copy of this book.', $exception->getMessage());
        }

        $this->assertDatabaseCount('borrowings', 1);
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'book_copy_id' => $book->copies()->firstOrFail()->id]);
        $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'ISSUED']);
    }

    public function test_same_title_can_be_issued_from_a_second_available_physical_copy(): void
    {
        $member = $this->member();
        $book = $this->book();
        BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'P6-SECOND-COPY-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => Carbon::today(),
        ]);
        $service = app(BorrowingService::class);

        $firstBorrowing = $service->issue($member, $book);
        $secondBorrowing = $service->issue($member, $book);

        $this->assertNotSame($firstBorrowing->book_copy_id, $secondBorrowing->book_copy_id);
        $this->assertDatabaseCount('borrowings', 2);
        $this->assertSame(0, $book->copies()->where('status', 'AVAILABLE')->count());
    }

    public function test_suspended_membership_cannot_issue_a_book(): void
    {
        $member = $this->member(status: 'SUSPENDED');
        $book = $this->book();

        try {
            app(BorrowingService::class)->issue($member, $book);
            $this->fail('A suspended member must not issue a book.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Member membership is not active or has expired.', $exception->getMessage());
        }

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertDatabaseHas('book_copies', ['book_id' => $book->id, 'status' => 'AVAILABLE']);
    }

    public function test_overdue_sync_and_return_keep_borrowing_and_copy_state_consistent(): void
    {
        $member = $this->member();
        $book = $this->book();
        $service = app(BorrowingService::class);
        $borrowing = $service->issue($member, $book);
        $borrowing->update(['due_date' => Carbon::yesterday()]);

        $service->syncOverdue();
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'OVERDUE']);
        $this->assertDatabaseCount('fines', 0);

        $returned = $service->returnBook($borrowing->fresh());
        $this->assertSame('RETURNED', $returned->status);
        $this->assertSame(Carbon::today()->toDateString(), $returned->return_date->toDateString());
        $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'AVAILABLE']);

        try {
            $service->returnBook($returned);
            $this->fail('A borrowing cannot be returned twice.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('This book has already been returned.', $exception->getMessage());
        }
        $this->assertDatabaseCount('borrowings', 1);
    }

    public function test_return_books_page_lists_the_librarian_return_action(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $librarian = User::factory()->create(['role' => User::ROLE_LIBRARIAN, 'is_active' => true]);

        $this->actingAs($librarian)
            ->get(route('librarian.returns.index'))
            ->assertOk()
            ->assertSee($book->title)
            ->assertSee($member->full_name)
            ->assertSee(route('librarian.returns.index'), false)
            ->assertSee(route('librarian.borrowings.return', $borrowing), false)
            ->assertDontSee('Return processing will be implemented in a later phase.');
    }

    public function test_children_and_elders_borrowings_can_be_returned(): void
    {
        foreach (['Children', 'Elders'] as $membershipTypeName) {
            $member = $this->member(membershipTypeName: $membershipTypeName);
            $book = $this->book();
            $borrowing = app(BorrowingService::class)->issue($member, $book);
            $librarian = User::factory()->create(['role' => User::ROLE_LIBRARIAN, 'is_active' => true]);

            $this->actingAs($librarian)
                ->from(route('librarian.returns.index'))
                ->post(route('librarian.borrowings.return', $borrowing))
                ->assertRedirect(route('librarian.returns.index'))
                ->assertSessionHas('success', 'Book returned successfully.');

            $this->assertDatabaseHas('borrowings', [
                'id' => $borrowing->id,
                'status' => 'RETURNED',
                'return_date' => today()->toDateString(),
            ]);
            $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'AVAILABLE']);
        }
    }

    public function test_duplicate_return_action_is_rejected_without_changing_the_returned_copy(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $librarian = User::factory()->create(['role' => User::ROLE_LIBRARIAN, 'is_active' => true]);

        $this->actingAs($librarian)
            ->from(route('librarian.returns.index'))
            ->post(route('librarian.borrowings.return', $borrowing))
            ->assertSessionHas('success', 'Book returned successfully.');

        $this->actingAs($librarian)
            ->from(route('librarian.returns.index'))
            ->post(route('librarian.borrowings.return', $borrowing))
            ->assertSessionHasErrors('borrowing');

        $this->assertDatabaseCount('renewals', 0);
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'RETURNED',
            'return_date' => today()->toDateString(),
        ]);
        $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'AVAILABLE']);
    }

    public function test_lost_borrowing_cannot_be_returned_normally(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $borrowing->update(['status' => 'LOST']);
        $borrowing->bookCopy->update(['status' => 'LOST']);

        try {
            app(BorrowingService::class)->returnBook($borrowing->fresh());
            $this->fail('A lost borrowing cannot be returned normally.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('A lost or damaged borrowing cannot be returned normally.', $exception->getMessage());
        }

        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'LOST', 'return_date' => null]);
        $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'LOST']);
    }

    public function test_fine_payment_and_fine_status_are_atomic_and_not_duplicated(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $service = app(PaymentService::class);
        $fine = $service->createFine($member, '9.50', 'OVERDUE', $borrowing->id);

        $payment = $service->payFine($fine);
        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'status' => 'PAID', 'paid_date' => today()->toDateString()]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'fine_id' => $fine->id, 'member_id' => $member->id, 'status' => 'PAID']);
        $this->assertSame('0.00', $service->outstandingBalance($member));

        try {
            $service->payFine($fine);
            $this->fail('A paid fine must not be paid twice.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('This fine has already been paid.', $exception->getMessage());
        }
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_paid_fine_cannot_be_waived_after_payment(): void
    {
        $member = $this->member();
        $fine = app(PaymentService::class)->createFine($member, '9.50', 'OTHER');
        $service = app(PaymentService::class);
        $service->payFine($fine);

        try {
            $service->waiveFine($fine);
            $this->fail('A paid fine cannot be waived.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('A paid fine cannot be waived.', $exception->getMessage());
        }

        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'status' => 'PAID']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_damage_marks_borrowing_damaged_and_preserves_copy_stock_state(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);

        $record = app(DamageRecordService::class)->reportDamage(
            $member,
            $borrowing,
            $borrowing->bookCopy,
            'MINOR',
            Carbon::today()->toDateString(),
            '5000.00',
            'Damaged during circulation'
        );

        $this->assertDatabaseHas('damage_records', [
            'id' => $record->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'status' => 'REPORTED',
            'payment_status' => 'UNPAID',
        ]);
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'DAMAGED']);
        $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'DAMAGED']);
        $this->assertSame(0, $book->copies()->where('status', 'AVAILABLE')->count());

        try {
            app(BorrowingService::class)->returnBook($borrowing->fresh());
            $this->fail('A damaged borrowing cannot be returned as a normal return.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('A lost or damaged borrowing cannot be returned normally.', $exception->getMessage());
        }
    }

    public function test_returned_borrowing_cannot_be_reported_as_damaged(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        app(BorrowingService::class)->returnBook($borrowing);

        $this->expectExceptionMessage('A returned borrowing cannot be marked damaged.');
        app(DamageRecordService::class)->reportDamage(
            $member,
            $borrowing->fresh(),
            $borrowing->bookCopy->fresh(),
            'MINOR',
            Carbon::today()->toDateString(),
            '5000.00',
            'Reported after return'
        );
    }

    public function test_unassigned_librarian_cannot_issue_a_sabha_owned_member_or_book(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'P6NULL', 'name' => 'Phase 6 Tenant Sabha']);
        [$member, $book] = app(PradeshiyaSabhaContext::class)->runFor(
            $sabha,
            fn () => [$this->member($sabha), $this->book($sabha)]
        );
        $unassignedLibrarian = User::factory()->create([
            'role' => User::ROLE_LIBRARIAN,
            'is_active' => true,
            'pradeshiya_sabha_id' => null,
        ]);

        $this->actingAs($unassignedLibrarian)
            ->post(route('librarian.borrowings.store'), [
                'member_id' => $member->id,
                'book_id' => $book->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertDatabaseHas('book_copies', ['book_id' => $book->id, 'status' => 'AVAILABLE']);
    }

    public function test_lost_copy_remains_unavailable_and_preserves_borrowing_history(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $lostBook = app(LostBookService::class)->reportLost(
            $member,
            $borrowing,
            $borrowing->bookCopy,
            Carbon::today()->toDateString(),
            '125.00',
            'Lost during checkout'
        );

        $this->assertDatabaseHas('lost_books', ['id' => $lostBook->id, 'borrowing_id' => $borrowing->id, 'book_copy_id' => $borrowing->book_copy_id]);
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'LOST']);
        $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'LOST']);
        $this->assertSame(0, $book->copies()->where('status', 'AVAILABLE')->count());
    }

    public function test_lost_copy_cannot_be_reclassified_as_damaged(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        app(LostBookService::class)->reportLost(
            $member,
            $borrowing,
            $borrowing->bookCopy,
            Carbon::today()->toDateString(),
            '125.00'
        );

        $this->expectExceptionMessage('A lost or damaged borrowing cannot be reported as damaged.');
        app(DamageRecordService::class)->reportDamage(
            $member,
            $borrowing->fresh(),
            $borrowing->bookCopy->fresh(),
            'MAJOR',
            Carbon::today()->toDateString(),
            '6000.00',
            'Invalid damage after loss'
        );
    }

    public function test_damaged_copy_cannot_be_reclassified_as_lost(): void
    {
        $member = $this->member();
        $book = $this->book();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        app(DamageRecordService::class)->reportDamage(
            $member,
            $borrowing,
            $borrowing->bookCopy,
            'MAJOR',
            Carbon::today()->toDateString(),
            '6000.00',
            'Reported damage'
        );

        $this->expectExceptionMessage('A lost or damaged borrowing cannot be marked lost.');
        app(LostBookService::class)->reportLost(
            $member,
            $borrowing->fresh(),
            $borrowing->bookCopy->fresh(),
            Carbon::today()->toDateString(),
            '125.00'
        );
    }

    public function test_sabha_admin_cannot_mutate_another_sabhas_circulation_records(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'P6A', 'name' => 'Phase 6 Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'P6B', 'name' => 'Phase 6 Sabha B']);
        $adminA = $this->sabhaAdmin($sabhaA);
        $memberB = $this->member($sabhaB);
        $bookB = $this->book($sabhaB);
        $copyB = $bookB->copies()->firstOrFail();
        $copyB->update(['status' => 'ISSUED']);
        $borrowingB = Borrowing::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'book_id' => $bookB->id,
            'book_copy_id' => $copyB->id,
            'issue_date' => today(),
            'due_date' => today()->addDays(14),
            'status' => 'ISSUED',
            'renewal_count' => 0,
        ]);
        $fineB = Fine::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'amount' => '10.00',
            'issued_date' => today(),
            'fine_type' => 'OVERDUE',
            'status' => 'UNPAID',
        ]);
        $paymentB = Payment::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'fine_id' => $fineB->id,
            'payment_type' => 'OVERDUE_FINE',
            'amount' => '10.00',
            'payment_method' => 'cash',
            'payment_date' => today(),
            'status' => 'PAID',
        ]);
        $lostB = LostBook::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'book_id' => $bookB->id,
            'book_copy_id' => $copyB->id,
            'lost_date' => today(),
            'replacement_cost' => '10.00',
            'compensation_amount' => '10.00',
            'status' => 'REPORTED',
            'payment_status' => 'UNPAID',
        ]);
        $damageB = DamageRecord::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'book_id' => $bookB->id,
            'book_copy_id' => $copyB->id,
            'damage_type' => 'MAJOR',
            'damage_date' => today(),
            'description' => 'Tenant isolation fixture',
            'repair_cost' => '10.00',
            'compensation_amount' => '10.00',
            'status' => 'REPORTED',
            'payment_status' => 'UNPAID',
        ]);

        $this->actingAs($adminA);
        $this->post(route('admin.borrowings.store'), ['member_id' => $memberB->id, 'book_id' => $bookB->id])->assertNotFound();
        $this->post(route('admin.borrowings.return', $borrowingB))->assertNotFound();
        $this->post(route('admin.borrowings.renew', $borrowingB))->assertNotFound();
        $this->post(route('admin.fines.pay', $fineB))->assertNotFound();
        $this->post(route('admin.fines.waive', $fineB))->assertNotFound();
        $this->post(route('admin.payments.membership'), [
            'member_id' => $memberB->id,
            'payment_type' => 'MONTHLY_MEMBERSHIP',
        ])->assertNotFound();
        $this->get(route('admin.payments.show', $paymentB))->assertNotFound();
        $this->post(route('admin.lost-books.pay', $lostB))->assertNotFound();
        $this->post(route('admin.damaged-books.pay', $damageB))->assertNotFound();
        $this->post(route('admin.lost-books.store'), [
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'book_copy_id' => $copyB->id,
            'lost_date' => today()->toDateString(),
            'compensation_amount' => '10.00',
        ])->assertNotFound();
        $this->post(route('admin.damaged-books.store'), [
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'book_copy_id' => $copyB->id,
            'damage_date' => today()->toDateString(),
            'damage_type' => 'MAJOR',
            'description' => 'Forged cross-tenant damage request',
            'compensation_amount' => '10.00',
        ])->assertNotFound();

        $this->assertDatabaseHas('borrowings', ['id' => $borrowingB->id, 'status' => 'ISSUED', 'renewal_count' => 0]);
        $this->assertDatabaseHas('fines', ['id' => $fineB->id, 'status' => 'UNPAID']);
        $this->assertDatabaseHas('payments', ['id' => $paymentB->id, 'status' => 'PAID']);
        $this->assertDatabaseHas('lost_books', ['id' => $lostB->id, 'status' => 'REPORTED']);
        $this->assertDatabaseHas('damage_records', ['id' => $damageB->id, 'status' => 'REPORTED']);
    }

    private function member(?PradeshiyaSabha $sabha = null, string $status = 'ACTIVE', string $membershipTypeName = 'Children'): Member
    {
        $type = MembershipType::query()->firstOrCreate(
            ['name' => $membershipTypeName],
            ['max_books' => $membershipTypeName === 'Elders' ? 3 : 2, 'description' => $membershipTypeName.' membership']
        );
        $user = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha?->id,
        ]);

        return Member::create([
            'user_id' => $user->id,
            'pradeshiya_sabha_id' => $sabha?->id,
            'membership_type_id' => $type->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'nic' => 'P6-NIC-'.$user->id,
            'phone' => '0771234567',
            'address' => 'Test address',
            'registration_fee' => '0.00',
            'registration_date' => today(),
            'membership_start_date' => today(),
            'next_renewal_date' => today()->addMonth(),
            'expiry_date' => today()->addMonth(),
            'status' => $status,
        ]);
    }

    private function book(?PradeshiyaSabha $sabha = null): Book
    {
        $author = Author::create(['name' => 'Phase 6 Author', 'pradeshiya_sabha_id' => $sabha?->id]);
        $publisher = Publisher::create(['name' => 'Phase 6 Publisher', 'pradeshiya_sabha_id' => $sabha?->id]);
        $category = Category::create(['code' => 'P6-100', 'name' => 'Phase 6 Category', 'pradeshiya_sabha_id' => $sabha?->id]);
        $book = Book::create([
            'book_id' => 'P6-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'pradeshiya_sabha_id' => $sabha?->id,
            'title' => 'Phase 6 Circulation Book',
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '100.1',
            'status' => 'available',
        ]);
        BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha?->id,
            'accession_number' => 'P6-COPY-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => Carbon::today(),
        ]);

        return $book->fresh();
    }

    private function sabhaAdmin(PradeshiyaSabha $sabha): User
    {
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

        return $admin;
    }
}