<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\LibrarySetting;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\Publisher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\PaymentService;
use Tests\TestCase;

class ReturnRenewalFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $membershipTypeName = 'Children', ?PradeshiyaSabha $sabha = null): array
    {
        $type = MembershipType::firstOrCreate(
            ['name' => $membershipTypeName],
            [
                'max_books' => $membershipTypeName === 'Elders' ? 3 : 2,
                'description' => $membershipTypeName,
            ]
        );
        $user = User::factory()->create([
            'role' => 'MEMBER',
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha?->id,
        ]);
        $today = Carbon::today();
        $member = Member::create([
            'user_id' => $user->id, 'pradeshiya_sabha_id' => $sabha?->id, 'membership_type_id' => $type->id, 'full_name' => $user->name,
            'email' => $user->email, 'nic' => 'NIC-' . $user->id, 'phone' => '0770000000', 'address' => 'Colombo',
            'registration_fee' => 220, 'registration_date' => $today, 'membership_start_date' => $today,
            'next_renewal_date' => $today->copy()->addMonth(), 'expiry_date' => $today->copy()->addMonth(), 'status' => 'ACTIVE',
        ]);
        $author = Author::create(['name' => 'Return Author']);
        $publisher = Publisher::create(['name' => 'Return Publisher']);
        $category = Category::create(['code' => '700', 'name' => 'Art']);
        $book = Book::create(['book_id' => 'RETURN-' . uniqid(), 'pradeshiya_sabha_id' => $sabha?->id, 'title' => 'Return Book', 'author_id' => $author->id, 'publisher_id' => $publisher->id, 'category_id' => $category->id, 'ddc_number' => '700.1', 'status' => 'available']);
        $copy = BookCopy::create(['book_id' => $book->id, 'pradeshiya_sabha_id' => $sabha?->id, 'accession_number' => 'RETURN-COPY-' . uniqid(), 'status' => 'ISSUED', 'condition' => 'GOOD', 'acquisition_date' => $today]);
        $borrowing = Borrowing::create(['member_id' => $member->id, 'pradeshiya_sabha_id' => $sabha?->id, 'book_id' => $book->id, 'book_copy_id' => $copy->id, 'issue_date' => $today->copy()->subDays(14), 'due_date' => $today, 'status' => 'ISSUED', 'renewal_count' => 0]);

        if ($sabha) {
            foreach ([
                'individual_borrowing_limit' => '2',
                'family_borrowing_limit' => '3',
                'issue_period_days' => '14',
                'maximum_renewals' => '2',
            ] as $key => $value) {
                PradeshiyaSabhaSetting::create([
                    'pradeshiya_sabha_id' => $sabha->id,
                    'key' => $key,
                    'value' => $value,
                ]);
            }
        }

        return [$member, $book, $copy, $borrowing];
    }

    public function test_admin_can_return_book_and_copy_becomes_available(): void
    {
        [$member, $book, $copy, $borrowing] = $this->fixture();
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($admin)->post("/librarian/borrowings/{$borrowing->id}/return")->assertRedirect();

        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'status' => 'RETURNED', 'return_date' => Carbon::today()->toDateString()]);
        $this->assertDatabaseHas('book_copies', ['id' => $copy->id, 'status' => 'AVAILABLE']);
    }

    public function test_renewals_page_lists_borrowings_instead_of_a_placeholder(): void
    {
        [$member, $book] = $this->fixture();
        $admin = User::factory()->create(['role' => User::ROLE_MAIN_ADMIN, 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/renewals')
            ->assertOk()
            ->assertSee('Review borrowing status and renewal history')
            ->assertSee($member->full_name)
            ->assertSee($book->title)
            ->assertDontSee('Renewal functionality will be implemented in a later phase.');
    }

    public function test_librarian_renewals_page_links_to_the_existing_renewal_action(): void
    {
        [, , , $borrowing] = $this->fixture();
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($librarian)
            ->get('/librarian/renewals')
            ->assertOk()
            ->assertSee(route('librarian.borrowings.renew', $borrowing), false);
    }

    public function test_librarian_reports_page_has_registered_routes(): void
    {
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($librarian)
            ->get('/librarian/reports')
            ->assertOk()
            ->assertSee(route('librarian.reports.show', 'borrowings'), false);
    }

    public function test_issue_and_return_keep_book_inventory_synchronized(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'INV'.substr(uniqid(), 0, 7), 'name' => 'Inventory Sabha']);
        [$member, $book] = $this->fixture('Children', $sabha);
        $availableCopy = BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'accession_number' => 'AVAILABLE-COPY-'.uniqid(),
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => Carbon::today(),
        ]);
        $librarian = User::factory()->create([
            'role' => 'LIBRARIAN',
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        $this->assertDatabaseHas('book_inventories', [
            'pradeshiya_sabha_id' => $sabha->id,
            'book_id' => $book->id,
            'total_quantity' => 2,
            'available_quantity' => 1,
        ]);

        $this->actingAs($librarian)
            ->post('/librarian/borrowings', ['member_id' => $member->id, 'book_id' => $book->id])
            ->assertRedirect('/librarian/borrowings');

        $borrowing = Borrowing::query()->where('book_copy_id', $availableCopy->id)->firstOrFail();
        $this->assertDatabaseHas('book_inventories', [
            'pradeshiya_sabha_id' => $sabha->id,
            'book_id' => $book->id,
            'total_quantity' => 2,
            'available_quantity' => 0,
        ]);

        $this->post("/librarian/borrowings/{$borrowing->id}/return")->assertRedirect();
        $this->assertDatabaseHas('book_inventories', [
            'pradeshiya_sabha_id' => $sabha->id,
            'book_id' => $book->id,
            'total_quantity' => 2,
            'available_quantity' => 1,
        ]);
    }

    public function test_children_and_elders_members_can_both_renew(): void
    {
        foreach (['Children', 'Elders'] as $membershipType) {
            [, , , $borrowing] = $this->fixture($membershipType);
            $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

            $this->actingAs($librarian)
                ->post("/librarian/borrowings/{$borrowing->id}/renew")
                ->assertRedirect()
                ->assertSessionHas('success', 'Book renewed successfully.');

            $this->assertDatabaseHas('borrowings', [
                'id' => $borrowing->id,
                'renewal_count' => 1,
                'due_date' => Carbon::today()->addDays(14)->toDateString(),
            ]);
        }
    }

    public function test_lost_damaged_and_inactive_borrowings_cannot_be_renewed(): void
    {
        foreach ([
            ['status' => 'LOST', 'copy_status' => 'LOST'],
            ['status' => 'DAMAGED', 'copy_status' => 'DAMAGED'],
            ['status' => 'INACTIVE', 'copy_status' => 'ISSUED'],
        ] as $state) {
            [, , $copy, $borrowing] = $this->fixture();
            $borrowing->update(['status' => $state['status']]);
            $copy->update(['status' => $state['copy_status']]);
            $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

            $this->actingAs($librarian)
                ->from('/librarian/renewals')
                ->post("/librarian/borrowings/{$borrowing->id}/renew")
                ->assertSessionHasErrors('borrowing');

            $this->assertDatabaseMissing('renewals', ['borrowing_id' => $borrowing->id]);
        }
    }

    public function test_expired_and_suspended_members_cannot_renew(): void
    {
        foreach (['EXPIRED', 'SUSPENDED'] as $status) {
            [$member, , , $borrowing] = $this->fixture();
            $member->update([
                'status' => $status === 'SUSPENDED' ? 'SUSPENDED' : 'ACTIVE',
                'expiry_date' => $status === 'EXPIRED' ? Carbon::yesterday() : Carbon::tomorrow(),
            ]);
            $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

            $this->actingAs($librarian)
                ->from('/librarian/renewals')
                ->post("/librarian/borrowings/{$borrowing->id}/renew")
                ->assertSessionHasErrors('borrowing');

            $this->assertDatabaseMissing('renewals', ['borrowing_id' => $borrowing->id]);
        }
    }

    public function test_inactive_member_account_cannot_renew(): void
    {
        [$member, , , $borrowing] = $this->fixture();
        $member->user->update(['is_active' => false]);
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($librarian)
            ->from('/librarian/renewals')
            ->post("/librarian/borrowings/{$borrowing->id}/renew")
            ->assertSessionHasErrors('borrowing');

        $this->assertDatabaseMissing('renewals', ['borrowing_id' => $borrowing->id]);
    }

    public function test_overdue_borrowings_and_members_with_unpaid_fines_cannot_renew(): void
    {
        [, , , $overdueBorrowing] = $this->fixture();
        $overdueBorrowing->update(['due_date' => Carbon::yesterday()]);
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);

        $this->actingAs($librarian)
            ->from('/librarian/renewals')
            ->post("/librarian/borrowings/{$overdueBorrowing->id}/renew")
            ->assertSessionHasErrors('borrowing');

        [$member, , , $borrowing] = $this->fixture();
        app(PaymentService::class)->createFine($member, '10.00', 'OVERDUE', $borrowing->id);

        $this->actingAs($librarian)
            ->from('/librarian/renewals')
            ->post("/librarian/borrowings/{$borrowing->id}/renew")
            ->assertSessionHasErrors('borrowing');

        $this->assertDatabaseMissing('renewals', ['borrowing_id' => $overdueBorrowing->id]);
        $this->assertDatabaseMissing('renewals', ['borrowing_id' => $borrowing->id]);
    }

    public function test_librarian_cannot_renew_a_borrowing_from_another_sabha(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'RENA', 'name' => 'Renewal Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'RENB', 'name' => 'Renewal Sabha B']);
        [, , , $borrowing] = $this->fixture('Children', $sabhaB);
        $librarian = User::factory()->create([
            'role' => 'LIBRARIAN',
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabhaA->id,
        ]);

        $this->actingAs($librarian)
            ->post("/librarian/borrowings/{$borrowing->id}/renew")
            ->assertNotFound();

        $this->assertDatabaseMissing('renewals', ['borrowing_id' => $borrowing->id]);
    }

    public function test_librarian_can_renew_twice_and_third_renewal_is_rejected(): void
    {
        [$member, $book, $copy, $borrowing] = $this->fixture();
        $librarian = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        LibrarySetting::updateOrCreate(['key' => 'issue_period_days'], ['value' => '14']);
        LibrarySetting::updateOrCreate(['key' => 'maximum_renewals'], ['value' => '5']);

        $this->actingAs($librarian)->post("/librarian/borrowings/{$borrowing->id}/renew")->assertRedirect();
        $this->assertDatabaseHas('renewals', [
            'borrowing_id' => $borrowing->id,
            'old_due_date' => Carbon::today()->toDateString(),
            'new_due_date' => Carbon::today()->addDays(14)->toDateString(),
        ]);
        $this->actingAs($librarian)->post("/librarian/borrowings/{$borrowing->id}/renew")->assertRedirect();
        $this->assertDatabaseHas('borrowings', ['id' => $borrowing->id, 'renewal_count' => 2]);
        $this->assertDatabaseCount('renewals', 2);
        $this->assertEquals(Carbon::today()->addDays(28)->toDateString(), $borrowing->fresh()->due_date->toDateString());

        $this->actingAs($librarian)->from('/librarian/borrowings')->post("/librarian/borrowings/{$borrowing->id}/renew")->assertSessionHasErrors('borrowing');
        $this->assertDatabaseCount('renewals', 2);
        $this->assertDatabaseHas('renewals', [
            'borrowing_id' => $borrowing->id,
            'old_due_date' => Carbon::today()->addDays(14)->toDateString(),
            'new_due_date' => Carbon::today()->addDays(28)->toDateString(),
        ]);
    }

    public function test_member_cannot_return_or_renew_and_sees_history(): void
    {
        [$member, $book, $copy, $borrowing] = $this->fixture();

        $this->actingAs($member->user)->post("/admin/borrowings/{$borrowing->id}/return")->assertRedirect('/member/dashboard');
        $this->actingAs($member->user)->post("/admin/borrowings/{$borrowing->id}/renew")->assertRedirect('/member/dashboard');
        $this->actingAs($member->user)->get('/member/borrowings')->assertOk()->assertSee('Return Book');
    }

    public function test_returned_borrowing_cannot_be_renewed_or_returned_again(): void
    {
        [$member, $book, $copy, $borrowing] = $this->fixture();
        $admin = User::factory()->create(['role' => 'LIBRARIAN', 'is_active' => true]);
        $this->actingAs($admin)->post("/librarian/borrowings/{$borrowing->id}/return");

        $this->actingAs($admin)->from('/librarian/borrowings')->post("/librarian/borrowings/{$borrowing->id}/return")->assertSessionHasErrors('borrowing');
        $this->actingAs($admin)->from('/librarian/borrowings')->post("/librarian/borrowings/{$borrowing->id}/renew")->assertSessionHasErrors('borrowing');
    }
}
