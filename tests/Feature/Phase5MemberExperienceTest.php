<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookInventory;
use App\Models\BookReservation;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Fine;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\Payment;
use App\Models\PradeshiyaSabha;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase5MemberExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_portal_dashboard_catalog_details_and_alerts_are_available_and_tenant_scoped(): void
    {
        $sabhaA = $this->sabha('P5A');
        $sabhaB = $this->sabha('P5B');
        $member = $this->member($sabhaA, 'Phase Five Member');
        $ownBook = $this->book($sabhaA, 'Phase Five Own Book');
        $otherBook = $this->book($sabhaB, 'Phase Five Other Sabha Book');
        $borrowing = $this->borrowing($member, $ownBook, dueDate: today()->addDays(3));
        $fine = $this->fine($member, $borrowing, '7.25');

        $this->actingAs($member->user)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee($member->member_id)
            ->assertSee('Membership Information')
            ->assertSee($ownBook->title)
            ->assertSee('Due soon')
            ->assertSee('My Alerts')
            ->assertSee('Available renewals')
            ->assertDontSee('Library Settings')
            ->assertDontSee('Author Management')
            ->assertDontSee($otherBook->title);

        $this->get(route('member.books.search', ['sabha_id' => $sabhaB->id, 'pradeshiya_sabha_id' => $sabhaB->id]))
            ->assertOk()
            ->assertSee($ownBook->title)
            ->assertDontSee($otherBook->title);

        $this->get(route('member.books.show', $ownBook))
            ->assertOk()
            ->assertSee($ownBook->title)
            ->assertSee('Availability')
            ->assertDontSee('Delete');

        $this->get(route('member.books.show', $otherBook))->assertNotFound();
        $this->get(route('member.alerts'))
            ->assertOk()
            ->assertSee($ownBook->title)
            ->assertSee($fine->fine_type);
    }

    public function test_member_dashboard_catalog_and_reservations_use_only_the_members_sabha(): void
    {
        $sabhaA = $this->sabha('P5J');
        $sabhaB = $this->sabha('P5K');
        $member = $this->member($sabhaA, 'Catalog Sabha Member');
        $ownBook = $this->book($sabhaA, 'Own Sabha Available Title');
        $otherBook = $this->book($sabhaB, 'Other Sabha Hidden Title');
        BookInventory::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'book_id' => $ownBook->id,
            'total_quantity' => 3,
            'available_quantity' => 2,
        ]);
        BookCopy::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'book_id' => $ownBook->id,
            'accession_number' => 'PRIVATE-INTERNAL-ACCESSION',
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);
        BookInventory::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'book_id' => $otherBook->id,
            'total_quantity' => 5,
            'available_quantity' => 4,
        ]);
        BookReservation::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'member_id' => $member->id,
            'book_id' => $ownBook->id,
            'status' => 'PENDING',
            'reserved_at' => now(),
        ]);
        BookReservation::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $member->id,
            'book_id' => $otherBook->id,
            'status' => 'PENDING',
            'reserved_at' => now(),
        ]);

        $this->actingAs($member->user)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee($member->member_id)
            ->assertSee($sabhaA->name)
            ->assertSee($ownBook->title)
            ->assertSee('My reservations')
            ->assertDontSee('PRIVATE-INTERNAL-ACCESSION')
            ->assertDontSee($otherBook->title);

        $this->get(route('member.books.search'))
            ->assertOk()
            ->assertSee($ownBook->title)
            ->assertDontSee('PRIVATE-INTERNAL-ACCESSION')
            ->assertDontSee($otherBook->title);
    }

    public function test_pending_membership_status_is_recognized_for_member_portal_restrictions(): void
    {
        $member = new Member(['status' => 'PENDING']);

        $this->assertSame('PENDING', $member->portalStatus());
    }

    public function test_suspended_members_cannot_renew_from_the_member_portal(): void
    {
        $sabha = $this->sabha('P5L');
        $member = $this->member($sabha, 'Suspended Portal Member');
        $book = $this->book($sabha, 'Suspended Member Borrowing');
        $this->configureRenewalSettings($sabha);
        $borrowing = $this->borrowing($member, $book);
        $member->update(['status' => 'SUSPENDED']);

        $this->actingAs($member->user)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Your membership is suspended')
            ->assertSee('SUSPENDED');
        $this->post(route('member.renewals.store', $borrowing))
            ->assertForbidden();
        $this->get(route('member.borrowings'))
            ->assertOk()
            ->assertDontSee(route('member.renewals.store', $borrowing));
    }

    public function test_member_borrowings_fines_payments_membership_and_profile_are_owned(): void
    {
        $sabhaA = $this->sabha('P5C');
        $sabhaB = $this->sabha('P5D');
        $memberA = $this->member($sabhaA, 'Portal Owner');
        $memberB = $this->member($sabhaB, 'Portal Other Member');
        $bookA = $this->book($sabhaA, 'Owner Borrowed Book');
        $bookB = $this->book($sabhaB, 'Other Member Borrowed Book');
        $borrowingA = $this->borrowing($memberA, $bookA);
        $borrowingB = $this->borrowing($memberB, $bookB);
        $fineA = $this->fine($memberA, $borrowingA, '6.50');
        $fineB = $this->fine($memberB, $borrowingB, '91.00');
        Payment::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'member_id' => $memberA->id,
            'borrowing_id' => $borrowingA->id,
            'payment_type' => 'OVERDUE_FINE',
            'amount' => '6.50',
            'payment_method' => 'cash',
            'payment_date' => today(),
            'status' => 'PAID',
            'reference_number' => 'P5-OWNER-PAYMENT',
        ]);
        Payment::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'payment_type' => 'OVERDUE_FINE',
            'amount' => '91.00',
            'payment_method' => 'cash',
            'payment_date' => today(),
            'status' => 'PAID',
            'reference_number' => 'P5-OTHER-PAYMENT',
        ]);

        $this->actingAs($memberA->user)
            ->get(route('member.borrowings', ['member_id' => $memberB->id]))
            ->assertOk()
            ->assertSee($bookA->title)
            ->assertDontSee($bookB->title);
        $this->get(route('member.fines', ['member_id' => $memberB->id, 'fine_id' => $fineB->id]))
            ->assertOk()
            ->assertSee($fineA->fine_type)
            ->assertSee('6.50')
            ->assertDontSee('91.00');
        $this->get(route('member.payments', ['member_id' => $memberB->id, 'payment_id' => 999999]))
            ->assertOk()
            ->assertSee('P5-OWNER-PAYMENT')
            ->assertDontSee('P5-OTHER-PAYMENT')
            ->assertDontSee('91.00');
        $this->get(route('member.membership', ['member_id' => $memberB->id]))
            ->assertOk()
            ->assertSee($memberA->member_id)
            ->assertSee($memberA->membershipType->name)
            ->assertDontSee($memberB->member_id);
        $this->get(route('member.profile', ['member_id' => $memberB->id]))
            ->assertOk()
            ->assertSee($memberA->full_name)
            ->assertDontSee($memberB->full_name);
    }

    public function test_member_can_renew_only_own_eligible_borrowing_and_forged_ownership_is_ignored(): void
    {
        $sabha = $this->sabha('P5E');
        $memberA = $this->member($sabha, 'Renewal Owner');
        $memberB = $this->member($sabha, 'Renewal Other Member');
        $otherSabha = $this->sabha('P5H');
        $memberC = $this->member($otherSabha, 'Renewal Cross Sabha Member');
        $bookA = $this->book($sabha, 'Renewal Owner Book');
        $bookB = $this->book($sabha, 'Renewal Other Book');
        $bookC = $this->book($otherSabha, 'Renewal Cross Sabha Book');
        $this->configureRenewalSettings($sabha);
        $borrowingA = $this->borrowing($memberA, $bookA);
        $borrowingB = $this->borrowing($memberB, $bookB);
        $borrowingC = $this->borrowing($memberC, $bookC);

        $this->actingAs($memberA->user)
            ->post(route('member.renewals.store', $borrowingB->id), [
                'member_id' => $memberA->id,
                'pradeshiya_sabha_id' => $sabha->id,
                'user_id' => $memberA->user_id,
            ])
            ->assertNotFound();
        $this->post(route('member.renewals.store', $borrowingC->id))->assertNotFound();

        $this->post(route('member.renewals.store', $borrowingA->id), [
            'member_id' => $memberB->id,
            'pradeshiya_sabha_id' => 999999,
            'user_id' => $memberB->user_id,
        ])->assertRedirect();

        $this->assertDatabaseHas('borrowings', ['id' => $borrowingA->id, 'member_id' => $memberA->id, 'renewal_count' => 1]);
        $this->assertDatabaseHas('borrowings', ['id' => $borrowingB->id, 'member_id' => $memberB->id, 'renewal_count' => 0]);
        $this->assertDatabaseHas('renewals', ['borrowing_id' => $borrowingA->id]);
    }

    public function test_member_password_change_requires_current_password_and_preserves_role_and_tenant(): void
    {
        $sabha = $this->sabha('P5F');
        $member = $this->member($sabha, 'Password Owner', password: 'Current-password-123');

        $this->actingAs($member->user)->get(route('member.password.edit'))->assertOk();
        $this->put(route('member.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'Next-password-123',
            'password_confirmation' => 'Next-password-123',
        ])->assertSessionHasErrors('current_password');
        $this->put(route('member.password.update'), [
            'current_password' => 'Current-password-123',
            'password' => 'Next-password-123',
            'password_confirmation' => 'Next-password-123',
            'role' => User::ROLE_SUPER_ADMIN,
            'pradeshiya_sabha_id' => null,
            'member_id' => 999999,
            'user_id' => 999999,
        ])->assertRedirect();

        $member->user->refresh();
        $this->assertTrue(Hash::check('Next-password-123', $member->user->password));
        $this->assertSame(User::ROLE_MEMBER, $member->user->role);
        $this->assertSame($sabha->id, (int) $member->user->pradeshiya_sabha_id);
    }

    public function test_member_cannot_renew_overdue_borrowings_or_with_unpaid_fines(): void
    {
        $sabha = $this->sabha('P5I');
        $memberWithFine = $this->member($sabha, 'Fine Restricted Member');
        $overdueMember = $this->member($sabha, 'Overdue Restricted Member');
        $fineBook = $this->book($sabha, 'Fine Restricted Book');
        $overdueBook = $this->book($sabha, 'Overdue Restricted Book');
        $this->configureRenewalSettings($sabha);
        $fineBorrowing = $this->borrowing($memberWithFine, $fineBook);
        $overdueBorrowing = $this->borrowing($overdueMember, $overdueBook, dueDate: today()->subDay());
        $this->fine($memberWithFine, $fineBorrowing, '5.00');

        $this->actingAs($memberWithFine->user)
            ->from(route('member.borrowings'))
            ->post(route('member.renewals.store', $fineBorrowing))
            ->assertRedirect(route('member.borrowings'))
            ->assertSessionHasErrors('borrowing');
        $this->actingAs($overdueMember->user)
            ->from(route('member.borrowings'))
            ->post(route('member.renewals.store', $overdueBorrowing))
            ->assertRedirect(route('member.borrowings'))
            ->assertSessionHasErrors('borrowing');
        $this->actingAs($memberWithFine->user)
            ->get(route('member.borrowings'))
            ->assertDontSee(route('member.renewals.store', $fineBorrowing));
        $this->actingAs($overdueMember->user)
            ->get(route('member.borrowings'))
            ->assertDontSee(route('member.renewals.store', $overdueBorrowing));

        $this->assertDatabaseHas('borrowings', ['id' => $fineBorrowing->id, 'renewal_count' => 0]);
        $this->assertDatabaseHas('borrowings', ['id' => $overdueBorrowing->id, 'renewal_count' => 0]);
    }

    public function test_null_tenant_member_does_not_get_global_portal_data(): void
    {
        $sabha = $this->sabha('P5G');
        $assignedMember = $this->member($sabha, 'Assigned Member');
        $assignedBook = $this->book($sabha, 'Assigned Sabha Secret Book');
        $assignedBorrowing = $this->borrowing($assignedMember, $assignedBook);
        $this->fine($assignedMember, $assignedBorrowing, '88.88');
        Payment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $assignedMember->id,
            'borrowing_id' => $assignedBorrowing->id,
            'payment_type' => 'OVERDUE_FINE',
            'amount' => '88.88',
            'payment_method' => 'cash',
            'payment_date' => today(),
            'status' => 'PAID',
            'reference_number' => 'P5-ASSIGNED-PAYMENT',
        ]);
        $legacyMember = $this->member(null, 'Legacy Null Tenant Member');

        $this->actingAs($legacyMember->user)
            ->get(route('member.books.search', ['pradeshiya_sabha_id' => $sabha->id]))
            ->assertOk()
            ->assertDontSee($assignedBook->title);
        $this->get(route('member.borrowings'))->assertOk()->assertDontSee($assignedBook->title);
        $this->get(route('member.fines'))->assertOk()->assertDontSee('88.88');
        $this->get(route('member.payments'))->assertOk()->assertDontSee('P5-ASSIGNED-PAYMENT')->assertDontSee('88.88');
        $this->get(route('member.profile'))->assertOk()->assertSee($legacyMember->full_name)->assertDontSee($assignedMember->full_name);
        $this->get(route('admin.dashboard'))->assertRedirect(route('member.dashboard'));
    }

    private function sabha(string $code): PradeshiyaSabha
    {
        return PradeshiyaSabha::create(['code' => $code, 'name' => 'Phase 5 Sabha '.$code]);
    }

    private function member(?PradeshiyaSabha $sabha, string $name, string $password = 'password'): Member
    {
        $type = MembershipType::query()->firstOrCreate(
            ['name' => 'Individual'],
            ['max_books' => 2, 'description' => 'Individual membership']
        );
        $email = strtolower(str_replace(' ', '.', $name)).'@example.test';
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha?->id,
        ]);

        return Member::create([
            'user_id' => $user->id,
            'pradeshiya_sabha_id' => $sabha?->id,
            'membership_type_id' => $type->id,
            'full_name' => $name,
            'email' => $email,
            'nic' => 'P5-NIC-'.$user->id,
            'phone' => '0771234567',
            'address' => 'Test address',
            'registration_fee' => '0.00',
            'registration_date' => today(),
            'membership_start_date' => today(),
            'next_renewal_date' => today()->addMonth(),
            'expiry_date' => today()->addMonth(),
            'status' => 'ACTIVE',
        ]);
    }

    private function book(PradeshiyaSabha $sabha, string $title): Book
    {
        $suffix = strtoupper(substr(md5($title.$sabha->id), 0, 8));
        $author = Author::create(['name' => $title.' Author', 'pradeshiya_sabha_id' => $sabha->id]);
        $publisher = Publisher::create(['name' => $title.' Publisher', 'pradeshiya_sabha_id' => $sabha->id]);
        $category = Category::create([
            'code' => 'P5-'.$suffix,
            'name' => $title.' Category',
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        return Book::create([
            'book_id' => 'P5-'.$suffix,
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => $title,
            'isbn' => '978-0-'.$suffix,
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'publication_year' => 2020,
            'edition' => '1st',
            'ddc_number' => '600.1',
            'status' => 'available',
        ]);
    }

    private function borrowing(Member $member, Book $book, ?\Illuminate\Support\Carbon $dueDate = null): Borrowing
    {
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $book->pradeshiya_sabha_id,
            'accession_number' => 'P5-COPY-'.strtoupper(substr(md5($book->id.$member->id), 0, 8)),
            'status' => 'ISSUED',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        return Borrowing::create([
            'pradeshiya_sabha_id' => $member->pradeshiya_sabha_id,
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => $copy->id,
            'issue_date' => today(),
            'due_date' => $dueDate ?? today()->addDays(14),
            'status' => 'ISSUED',
            'renewal_count' => 0,
        ]);
    }

    private function fine(Member $member, Borrowing $borrowing, string $amount): Fine
    {
        return Fine::create([
            'pradeshiya_sabha_id' => $member->pradeshiya_sabha_id,
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'amount' => $amount,
            'issued_date' => today(),
            'fine_type' => 'OVERDUE',
            'status' => 'UNPAID',
        ]);
    }

    private function configureRenewalSettings(PradeshiyaSabha $sabha): void
    {
        foreach (['issue_period_days' => '14', 'maximum_renewals' => '2'] as $key => $value) {
            \App\Models\PradeshiyaSabhaSetting::create([
                'pradeshiya_sabha_id' => $sabha->id,
                'key' => $key,
                'value' => $value,
            ]);
        }
    }
}