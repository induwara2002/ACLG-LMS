<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\Publisher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setupCatalog(): Book
    {
        $author = Author::create(['name' => 'Author']);
        $publisher = Publisher::create(['name' => 'Publisher']);
        $category = Category::firstOrCreate(['code' => '600'], ['name' => 'Technology']);
        $book = Book::create([
            'book_id' => 'BK-' . uniqid(), 'title' => 'Borrowable Book', 'isbn' => null,
            'author_id' => $author->id, 'publisher_id' => $publisher->id, 'category_id' => $category->id,
            'ddc_number' => '600.1', 'status' => 'available',
        ]);
        BookCopy::create([
            'book_id' => $book->id, 'accession_number' => 'COPY-' . uniqid(),
            'status' => 'AVAILABLE', 'condition' => 'GOOD', 'acquisition_date' => Carbon::today(),
        ]);

        return $book;
    }

    private function setupMember(string $type = 'Individual', string $status = 'ACTIVE'): Member
    {
        $membershipType = MembershipType::firstOrCreate([
            'name' => $type,
        ], [
            'max_books' => $type === 'Family' ? 3 : 2,
            'description' => $type . ' membership',
        ]);
        $user = User::factory()->create(['role' => 'MEMBER', 'is_active' => true]);
        $today = Carbon::today();

        return Member::create([
            'user_id' => $user->id, 'membership_type_id' => $membershipType->id,
            'full_name' => $user->name, 'email' => $user->email, 'nic' => 'NIC-' . $user->id,
            'phone' => '0770000000', 'address' => 'Colombo', 'registration_fee' => 220,
            'registration_date' => $today, 'membership_start_date' => $today,
            'next_renewal_date' => $today->copy()->addMonth(), 'expiry_date' => $today->copy()->addMonth(),
            'status' => $status,
        ]);
    }

    public function test_admin_can_issue_book_and_updates_copy(): void
    {
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->setupMember();
        $book = $this->setupCatalog();

        $this->actingAs($admin)->post('/librarian/borrowings', [
            'member_id' => $member->id, 'book_id' => $book->id,
        ])->assertRedirect('/librarian/borrowings');

        $borrowing = Borrowing::firstOrFail();
        $this->assertSame('ISSUED', $borrowing->status);
        $this->assertSame(0, $borrowing->renewal_count);
        $this->assertEquals(Carbon::today()->toDateString(), $borrowing->issue_date->toDateString());
        $this->assertNotNull($borrowing->issue_at);
        $this->assertEquals(Carbon::today()->addDays(14)->toDateString(), $borrowing->due_date->toDateString());
        $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'ISSUED']);
    }

    public function test_librarian_can_issue_but_member_cannot(): void
    {
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->setupMember();
        $book = $this->setupCatalog();

        $this->actingAs($librarian)->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $book->id])->assertRedirect('/librarian/borrowings');
        $this->assertCount(1, Borrowing::all());

        $this->actingAs($member->user)->post('/admin/borrowings', ['member_id' => $member->id, 'book_id' => $book->id])->assertRedirect('/member/dashboard');
    }

    public function test_individual_limit_and_expired_member_are_enforced(): void
    {
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->setupMember();
        $first = $this->setupCatalog();
        $second = $this->setupCatalog();
        $third = $this->setupCatalog();

        foreach ([$first, $second] as $book) {
            $this->actingAs($admin)->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $book->id])->assertRedirect();
        }
        $response = $this->actingAs($admin)->from('/librarian/borrowings/create')->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $third->id]);
        $response->assertSessionHasErrors('issue');
        $this->assertSame(2, Borrowing::count());

        $member->update(['expiry_date' => Carbon::yesterday()]);
        $response = $this->actingAs($admin)->from('/librarian/borrowings/create')->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $third->id]);
        $response->assertSessionHasErrors('issue');
    }

    public function test_unavailable_copy_is_rejected_and_member_sees_only_own_records(): void
    {
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->setupMember();
        $other = $this->setupMember('Family');
        $book = $this->setupCatalog();
        $book->copies()->update(['status' => 'LOST']);

        $this->actingAs($admin)->from('/librarian/borrowings/create')->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $book->id])->assertSessionHasErrors('issue');
        $borrowing = Borrowing::create([
            'member_id' => $other->id, 'book_id' => $book->id, 'book_copy_id' => $book->copies()->first()->id,
            'issue_date' => Carbon::today(), 'due_date' => Carbon::today()->addDays(14), 'status' => 'ISSUED', 'renewal_count' => 0,
        ]);
        $this->actingAs($member->user)->get('/member/borrowings')->assertOk()->assertDontSee($borrowing->bookCopy->accession_number);
    }

    public function test_family_member_can_borrow_three_but_not_four_books(): void
    {
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->setupMember('Family');
        $books = [$this->setupCatalog(), $this->setupCatalog(), $this->setupCatalog(), $this->setupCatalog()];

        foreach (array_slice($books, 0, 3) as $book) {
            $this->actingAs($admin)->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $book->id])->assertRedirect();
        }

        $this->actingAs($admin)->from('/librarian/borrowings/create')->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $books[3]->id])->assertSessionHasErrors('issue');
        $this->assertSame(3, Borrowing::count());
    }

    public function test_issue_and_return_times_are_recorded_by_the_server(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:20:30', 'Asia/Colombo'));

        try {
            $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
            $member = $this->setupMember();
            $book = $this->setupCatalog();

            $this->actingAs($librarian)->post('/librarian/borrowings', [
                'member_id' => $member->id,
                'book_id' => $book->id,
                'issue_date' => '1999-01-01',
                'issue_at' => '1999-01-01 00:00:00',
                'due_date' => '1999-01-02',
            ])->assertRedirect('/librarian/borrowings');

            $borrowing = Borrowing::firstOrFail();
            $this->assertSame('2026-10-14 10:20:30', $borrowing->issue_at->format('Y-m-d H:i:s'));
            $this->assertSame('2026-10-14', $borrowing->issue_date->toDateString());
            $borrowing->update(['due_date' => Carbon::yesterday()->toDateString()]);
            $this->actingAs($librarian)
                ->get('/librarian/returns?search='.$borrowing->id)
                ->assertOk()
                ->assertSee('Borrowable Book')
                ->assertSee('2026-10-14 10:20:30');

            $this->actingAs($librarian)->post('/librarian/borrowings/'.$borrowing->id.'/return', [
                'return_date' => '1999-01-01',
                'returned_at' => '1999-01-01 00:00:00',
            ])->assertRedirect();

            $borrowing->refresh();
            $this->assertSame('2026-10-14 10:20:30', $borrowing->returned_at->format('Y-m-d H:i:s'));
            $this->assertSame('RETURNED', $borrowing->status);
            $this->assertTrue($borrowing->wasOverdue());
            $this->assertDatabaseHas('book_copies', ['id' => $borrowing->book_copy_id, 'status' => 'AVAILABLE']);

            $this->actingAs($librarian)
                ->from('/librarian/returns')
                ->post('/librarian/borrowings/'.$borrowing->id.'/return')
                ->assertSessionHasErrors('borrowing');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_staff_can_lookup_actual_member_and_book_details(): void
    {
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->setupMember();
        $book = $this->setupCatalog();

        $this->actingAs($librarian)
            ->get('/librarian/issue-books')
            ->assertOk()
            ->assertSee('librarian\\/members\\/lookup', false)
            ->assertSee('librarian\\/books\\/lookup', false);

        $this->actingAs($librarian)
            ->getJson('/librarian/members/lookup?member_id='.urlencode($member->member_id))
            ->assertOk()
            ->assertJsonPath('member_id', $member->member_id)
            ->assertJsonPath('name', $member->full_name)
            ->assertJsonPath('status', 'ACTIVE')
            ->assertJsonPath('eligible', true);

        $this->actingAs($librarian)
            ->getJson('/librarian/books/lookup?search='.urlencode($book->book_id))
            ->assertOk()
            ->assertJsonPath('0.book_id', $book->book_id)
            ->assertJsonPath('0.available_copies', 1)
            ->assertJsonPath('0.available', true);
    }

    public function test_membership_expiry_falls_back_to_one_year_from_membership_start(): void
    {
        $member = $this->setupMember();
        $startDate = Carbon::parse('2025-10-14');
        $member->update([
            'membership_start_date' => $startDate,
            'expiry_date' => null,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-14'));
        try {
            $this->assertSame('ACTIVE', $member->fresh()->effectiveStatus());
            $this->assertSame('2026-10-14', $member->fresh()->effectiveExpiryDate()->toDateString());
            Carbon::setTestNow(Carbon::parse('2026-10-15'));
            $this->assertSame('EXPIRED', $member->fresh()->effectiveStatus());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_copy_with_an_existing_active_borrowing_cannot_be_issued_again(): void
    {
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->setupMember();
        $existingMember = $this->setupMember('Family');
        $book = $this->setupCatalog();
        $copy = $book->copies()->firstOrFail();

        Borrowing::create([
            'member_id' => $existingMember->id,
            'book_id' => $book->id,
            'book_copy_id' => $copy->id,
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(14),
            'status' => 'ISSUED',
            'renewal_count' => 0,
        ]);

        $this->actingAs($librarian)
            ->from('/librarian/issue-books')
            ->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $book->id])
            ->assertSessionHasErrors('issue');

        $this->assertSame(1, Borrowing::count());
        $this->assertSame('AVAILABLE', $copy->fresh()->status);
    }
}
