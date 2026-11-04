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

class Phase11DashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $type = 'Individual'): Member
    {
        $membershipType = MembershipType::firstOrCreate(['name' => $type], ['max_books' => strtoupper($type) === 'FAMILY' ? 3 : 2]);
        $user = User::factory()->create(['role' => 'MEMBER', 'is_active' => true]);
        $today = Carbon::today();

        return Member::create([
            'user_id' => $user->id,
            'membership_type_id' => $membershipType->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'nic' => 'NIC-'.$user->id,
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

    private function bookWithCopies(array $statuses = ['AVAILABLE']): Book
    {
        $book = Book::create([
            'book_id' => 'BK-'.fake()->unique()->numerify('#####'),
            'title' => 'Phase 11 Book',
            'isbn' => fake()->unique()->numerify('978##########'),
            'edition' => '1st',
            'author_id' => Author::create(['name' => 'Phase 11 Author'])->id,
            'publisher_id' => Publisher::create(['name' => 'Phase 11 Publisher'])->id,
            'category_id' => Category::create(['name' => 'Phase 11 Category'])->id,
            'publication_year' => 2026,
            'language' => 'English',
            'ddc_number' => '600',
            'shelf_number' => 'P11',
            'status' => 'ACTIVE',
        ]);

        foreach ($statuses as $index => $status) {
            BookCopy::create([
                'book_id' => $book->id,
                'accession_number' => 'P11-'.$book->id.'-'.$index,
                'status' => $status,
                'condition' => 'GOOD',
                'acquisition_date' => Carbon::today(),
            ]);
        }

        return $book;
    }

    public function test_roles_can_access_only_their_dashboards(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $member = $this->member();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAs($librarian)->get('/librarian/dashboard')->assertOk();
        $this->actingAs($member->user)->get('/member/dashboard')->assertOk();
        $this->actingAs($member->user)->get('/admin/dashboard')->assertRedirect('/member/dashboard');
        $this->actingAs($member->user)->get('/admin/reports')->assertRedirect('/member/dashboard');
    }

    public function test_dashboard_inventory_counts_use_book_copy_statuses(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $this->bookWithCopies(['AVAILABLE', 'ISSUED', 'LOST', 'DAMAGED', 'REPAIR']);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Available copies')
            ->assertSee('Lost copies');
    }

    public function test_member_dashboard_uses_authenticated_member_and_calculates_allowance(): void
    {
        $member = $this->member('Individual');
        $book = $this->bookWithCopies(['AVAILABLE']);
        $copy = $book->copies()->first();
        Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => $copy->id,
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(14),
            'status' => 'ISSUED',
            'renewal_count' => 0,
        ]);

        $this->actingAs($member->user)->get('/member/dashboard')
            ->assertOk()
            ->assertSee('Remaining allowance')
            ->assertSee('1');
    }

    public function test_dashboard_membership_statistics_and_alerts_use_derived_expiry_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'Asia/Colombo'));

        try {
            $admin = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
            $member = $this->member();
            $member->update([
                'registration_date' => Carbon::parse('2025-10-12'),
                'membership_start_date' => Carbon::parse('2025-10-12'),
                'expiry_date' => null,
                'status' => 'ACTIVE',
            ]);

            $this->actingAs($admin)
                ->get('/admin/dashboard')
                ->assertOk()
                ->assertViewHas('memberStats', fn ($stats) => $stats['expired'] === 1 && $stats['active'] === 0)
                ->assertViewHas('alerts', fn ($alerts) => $alerts['expiredMembers'] === 1
                    && $alerts['membershipRenewals']->contains(
                        fn (Member $candidate) => $candidate->id === $member->id
                            && $candidate->effectiveExpiryDate()->toDateString() === '2026-10-12'
                    ));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_family_membership_uses_three_book_limit(): void
    {
        $member = $this->member('Family');
        $this->actingAs($member->user)->get('/member/dashboard')->assertOk()->assertSee('3');
    }

    public function test_books_report_is_database_backed_and_paginates(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);
        $this->bookWithCopies();

        $this->actingAs($admin)->get('/admin/reports/books?search=Phase+11&page=1')
            ->assertOk()
            ->assertSee('Phase 11 Book')
            ->assertSee('page=1');
    }

    public function test_operational_reports_are_available_to_librarians(): void
    {
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        foreach (['books', 'members', 'borrowings', 'overdue', 'returns', 'lost-books', 'damaged-books'] as $report) {
            $this->actingAs($librarian)->get('/librarian/reports/'.$report)->assertOk();
        }
    }

    public function test_invalid_report_date_range_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/reports/borrowings?date_from=2026-09-20&date_to=2026-09-01')
            ->assertSessionHasErrors('date_from');
    }
}
