<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\Publisher;
use App\Models\User;
use App\Services\BorrowingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class MemberApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sabha_admin_can_only_view_and_approve_applications_from_their_sabha(): void
    {
        $sabhaA = $this->sabha('APPA');
        $sabhaB = $this->sabha('APPB');
        $adminA = $this->sabhaAdmin($sabhaA);
        $memberA = $this->member($sabhaA, 'Applicant Sabha A');
        $memberB = $this->member($sabhaB, 'Applicant Sabha B');

        $this->actingAs($adminA)
            ->get(route('admin.member-applications.index'))
            ->assertOk()
            ->assertSee($memberA->full_name)
            ->assertDontSee($memberB->full_name);

        $this->get(route('admin.member-applications.show', $memberB->id))->assertNotFound();
        $this->post(route('admin.member-applications.approve', $memberB->id))->assertNotFound();
        $this->post(route('admin.member-applications.reject', $memberB->id), [
            'rejection_reason' => 'This should not cross Sabha boundaries.',
        ])->assertNotFound();
        $this->assertDatabaseHas('members', ['id' => $memberB->id, 'status' => 'PENDING']);
    }

    public function test_approval_activates_membership_and_records_approver_and_audit_event(): void
    {
        $sabha = $this->sabha('APPC');
        $admin = $this->sabhaAdmin($sabha);
        $member = $this->member($sabha, 'Approved Portal Applicant');
        $book = $this->book($sabha, 'Approved Applicant Borrowing');
        BookCopy::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'book_id' => $book->id,
            'accession_number' => 'APP-APPROVED-COPY',
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.member-applications.approve', $member->id))
            ->assertRedirect(route('admin.member-applications.show', $member->id))
            ->assertSessionHas('success');

        $member->refresh();
        $this->assertSame('ACTIVE', $member->status);
        $this->assertSame($admin->id, (int) $member->approved_by);
        $this->assertNotNull($member->approved_at);
        $this->assertSame(today()->toDateString(), $member->membership_start_date->toDateString());
        $this->assertNotNull($member->expiry_date);
        $borrowing = app(BorrowingService::class)->issue($member, $book);
        $this->assertSame($member->id, $borrowing->member_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'member.application.approved',
            'auditable_type' => $member->getMorphClass(),
            'auditable_id' => $member->id,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
    }

    public function test_rejection_requires_a_reason_and_displays_it_to_the_member(): void
    {
        $sabha = $this->sabha('APPD');
        $admin = $this->sabhaAdmin($sabha);
        $member = $this->member($sabha, 'Rejected Portal Applicant');

        $this->actingAs($admin)
            ->post(route('admin.member-applications.reject', $member->id), ['rejection_reason' => 'No'])
            ->assertSessionHasErrors('rejection_reason');
        $this->post(route('admin.member-applications.reject', $member->id), [
            'rejection_reason' => 'Please provide the missing proof of address.',
        ])->assertRedirect(route('admin.member-applications.show', $member->id));

        $member->refresh();
        $this->assertSame('REJECTED', $member->status);
        $this->assertSame($admin->id, (int) $member->rejected_by);
        $this->assertNotNull($member->rejected_at);
        $this->assertSame('Please provide the missing proof of address.', $member->rejection_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'member.application.rejected',
            'auditable_id' => $member->id,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        $this->actingAs($member->user)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee('Your membership application was not approved.')
            ->assertSee($member->rejection_reason)
            ->assertSee($sabha->name);
    }

    public function test_pending_application_cannot_borrow_and_member_cannot_change_assigned_sabha(): void
    {
        $sabhaA = $this->sabha('APPE');
        $sabhaB = $this->sabha('APPF');
        $member = $this->member($sabhaA, 'Pending Circulation Applicant');
        $book = $this->book($sabhaA, 'Pending Circulation Book');

        BookCopy::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'book_id' => $book->id,
            'accession_number' => 'APP-PENDING-COPY',
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        try {
            app(BorrowingService::class)->issue($member, $book);
            $this->fail('A pending applicant must not be able to borrow a book.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Member membership is not active or has expired.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('borrowings', ['member_id' => $member->id]);
        $this->actingAs($member->user)
            ->put(route('member.password.update'), [
                'current_password' => 'password',
                'password' => 'Updated-password-123',
                'password_confirmation' => 'Updated-password-123',
                'pradeshiya_sabha_id' => $sabhaB->id,
                'status' => 'ACTIVE',
                'role' => User::ROLE_MAIN_ADMIN,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'status' => 'PENDING',
            'pradeshiya_sabha_id' => $sabhaA->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $member->user_id,
            'role' => User::ROLE_MEMBER,
            'pradeshiya_sabha_id' => $sabhaA->id,
        ]);
    }

    private function sabha(string $code): PradeshiyaSabha
    {
        return PradeshiyaSabha::create([
            'code' => $code,
            'name' => 'Application Sabha '.$code,
            'is_active' => true,
        ]);
    }

    private function sabhaAdmin(PradeshiyaSabha $sabha): User
    {
        $admin = User::create([
            'name' => 'Admin '.$sabha->code,
            'email' => strtolower($sabha->code).'@admin.example.test',
            'password' => Hash::make('password'),
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

    private function member(PradeshiyaSabha $sabha, string $name): Member
    {
        $type = MembershipType::query()->firstOrCreate(
            ['name' => 'Children'],
            ['max_books' => 3, 'description' => 'Children membership']
        );
        $email = strtolower(str_replace(' ', '.', $name)).'@example.test';
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        return Member::create([
            'user_id' => $user->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'membership_type_id' => $type->id,
            'full_name' => $name,
            'email' => $email,
            'nic' => 'APP-NIC-'.$user->id,
            'phone' => '0771234567',
            'address' => 'Application test address',
            'registration_date' => today(),
            'membership_start_date' => today(),
            'status' => 'PENDING',
        ]);
    }

    private function book(PradeshiyaSabha $sabha, string $title): Book
    {
        $author = Author::create(['name' => $title.' Author', 'pradeshiya_sabha_id' => $sabha->id]);
        $publisher = Publisher::create(['name' => $title.' Publisher', 'pradeshiya_sabha_id' => $sabha->id]);
        $category = Category::create([
            'code' => 'APP-'.strtoupper(substr(md5($title), 0, 8)),
            'name' => $title.' Category',
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        return Book::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'book_id' => 'APP-'.strtoupper(substr(md5($title.$sabha->id), 0, 8)),
            'title' => $title,
            'isbn' => '978-'.strtoupper(substr(md5($title.$sabha->id), 0, 10)),
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'publication_year' => 2020,
            'ddc_number' => '600.1',
            'status' => 'available',
        ]);
    }
}
