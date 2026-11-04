<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\DamageRecord;
use App\Models\LostBook;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\Publisher;
use App\Models\User;
use App\Services\BorrowingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase9LostDamagedTest extends TestCase
{
    use RefreshDatabase;

    private function makeMember(): Member
    {
        $type = MembershipType::firstOrCreate(['name' => 'Individual'], ['max_books' => 2, 'description' => 'Individual']);
        $user = User::factory()->create(['role' => 'MEMBER', 'is_active' => true]);
        $today = Carbon::today();

        return Member::create([
            'user_id' => $user->id,
            'membership_type_id' => $type->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'nic' => 'NIC-' . $user->id,
            'phone' => '0770000000',
            'address' => 'Colombo',
            'registration_fee' => 220,
            'registration_date' => $today,
            'membership_start_date' => $today,
            'next_renewal_date' => $today->copy()->addMonth(),
            'expiry_date' => $today->copy()->addMonth(),
            'status' => 'ACTIVE',
        ]);
    }

    private function makeBook(): Book
    {
        $author = Author::create(['name' => 'Author A']);
        $publisher = Publisher::create(['name' => 'Publisher A']);
        $category = Category::create(['name' => 'Fiction', 'parent_id' => null]);

        $book = Book::create([
            'book_id' => 'BK-1001',
            'title' => 'Lost and Damaged Book',
            'isbn' => '978-1-234567-89-0',
            'edition' => '1st',
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'publication_year' => 2024,
            'language' => 'English',
            'ddc_number' => '000',
            'shelf_number' => 'A1',
            'description' => 'Test book',
            'status' => 'ACTIVE',
        ]);

        BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'AC-1001',
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => Carbon::today(),
        ]);

        return $book->fresh();
    }

    public function test_admin_can_report_lost_book_and_book_copy_status_changes_to_lost(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($admin)->post('/librarian/lost-books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'lost_date' => Carbon::today()->toDateString(),
            'compensation_amount' => 250.00,
            'notes' => 'Lost during checkout',
        ])->assertRedirect();

        $this->assertDatabaseHas('lost_books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'status' => 'REPORTED',
        ]);

        $this->assertSame('LOST', $borrowing->fresh()->bookCopy->fresh()->status);
    }

    public function test_member_cannot_create_lost_book_record(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();
        $borrowing = app(BorrowingService::class)->issue($member, $book);

        $this->actingAs($member->user)->post('/admin/lost-books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'lost_date' => Carbon::today()->toDateString(),
            'compensation_amount' => 250.00,
            'notes' => 'Lost during checkout',
        ])->assertRedirect('/member/dashboard');

        $this->assertDatabaseCount('lost_books', 0);
    }

    public function test_lost_compensation_payment_creates_lost_book_compensation_payment(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($admin)->post('/librarian/lost-books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'lost_date' => Carbon::today()->toDateString(),
            'compensation_amount' => 250.00,
            'notes' => 'Lost during checkout',
        ]);

        $lostBook = LostBook::first();
        $this->actingAs($admin)->post('/librarian/lost-books/' . $lostBook->id . '/pay')->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'member_id' => $member->id,
            'payment_type' => 'LOST_BOOK_COMPENSATION',
            'amount' => 250.00,
            'status' => 'PAID',
        ]);

        $this->assertDatabaseHas('lost_books', ['id' => $lostBook->id, 'payment_status' => 'PAID']);
    }

    public function test_minor_damage_below_5000_is_rejected_and_5000_is_accepted(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($admin)->from('/librarian/lost-damaged/create-damaged')->post('/librarian/damaged-books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'damage_date' => Carbon::today()->toDateString(),
            'damage_type' => 'MINOR',
            'description' => 'Minor page damage',
            'compensation_amount' => 4000,
        ])->assertSessionHasErrors('compensation_amount');

        $this->actingAs($admin)->post('/librarian/damaged-books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'damage_date' => Carbon::today()->toDateString(),
            'damage_type' => 'MINOR',
            'description' => 'Minor page damage',
            'compensation_amount' => 5000,
        ])->assertRedirect();

        $this->assertDatabaseHas('damage_records', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'damage_type' => 'MINOR',
            'compensation_amount' => 5000.00,
            'status' => 'REPORTED',
        ]);
    }

    public function test_damaged_book_copy_cannot_be_issued_and_damage_compensation_payment_works(): void
    {
        $member = $this->makeMember();
        $otherMember = $this->makeMember();
        $book = $this->makeBook();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($admin)->post('/librarian/damaged-books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'damage_date' => Carbon::today()->toDateString(),
            'damage_type' => 'MAJOR',
            'description' => 'Major cover damage',
            'compensation_amount' => 6000,
        ]);

        $damage = DamageRecord::first();
        $this->assertSame('DAMAGED', $borrowing->fresh()->bookCopy->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        app(BorrowingService::class)->issue($otherMember, $book);

        $this->actingAs($admin)->post('/librarian/damaged-books/' . $damage->id . '/pay')->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'member_id' => $member->id,
            'payment_type' => 'DAMAGE_COMPENSATION',
            'amount' => 6000.00,
            'status' => 'PAID',
        ]);
    }

    public function test_member_can_only_view_own_lost_and_damage_records(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();
        $book = $this->makeBook();
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($admin)->post('/librarian/lost-books', [
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'lost_date' => Carbon::today()->toDateString(),
            'compensation_amount' => 150.00,
            'notes' => 'Lost during checkout',
        ]);

        $this->actingAs($member->user)->get('/member/lost-books')->assertOk()->assertSee('Lost and Damaged Book');
        $this->actingAs($member->user)->get('/member/damaged-books')->assertOk();
        $this->actingAs($member->user)->get('/member/lost-books')->assertDontSee($other->full_name);
    }
}
