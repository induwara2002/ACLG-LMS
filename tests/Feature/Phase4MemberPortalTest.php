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
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\Payment;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase4MemberPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_book_search_ignores_a_forged_sabha_filter(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'MEMA', 'name' => 'Member Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'MEMB', 'name' => 'Member Sabha B']);
        $member = $this->member($sabhaA, 'Member A');
        $bookA = $this->book($sabhaA, 'Member Search Marker Book A');
        $bookB = $this->book($sabhaB, 'Member Search Marker Book B');

        $this->actingAs($member->user)
            ->get(route('member.books.search', ['sabha_id' => $sabhaB->id, 'search' => 'Member Search Marker Book']))
            ->assertOk()
            ->assertSee($bookA->title)
            ->assertDontSee($bookB->title);
    }

    public function test_member_without_sabha_assignment_cannot_browse_tenant_catalogs(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'MEMNULL', 'name' => 'Assigned Catalog Sabha']);
        $book = $this->book($sabha, 'Assigned Sabha Private Catalog Book');
        $unassignedMember = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => null,
        ]);

        $this->actingAs($unassignedMember)
            ->get(route('member.books.search', ['search' => 'Assigned Sabha Private Catalog Book']))
            ->assertOk()
            ->assertViewHas('books', fn ($books) => $books->total() === 0)
            ->assertSee('No books match the selected filters.');
    }

    public function test_sabha_admin_creates_member_account_with_generated_identity_and_configured_payment(): void
    {
        [$sabhaA, $adminA] = $this->sabhaAdmin('ADMIN-A');
        [$sabhaB] = $this->sabhaAdmin('ADMIN-B');
        $membershipType = MembershipType::query()->firstOrCreate(
            ['name' => 'Children'],
            ['max_books' => 2, 'description' => 'Children membership']
        );
        $this->configureSettings($sabhaA);

        $this->actingAs($adminA)
            ->post(route('admin.members.store'), [
                'full_name' => 'Staff Registered Member',
                'email' => 'staff-member@example.test',
                'password' => 'member-initial-password',
                'password_confirmation' => 'member-initial-password',
                'nic' => 'PH4-STAFF-001',
                'phone' => '0771112222',
                'address' => 'Sabha A address',
                'membership_type_id' => $membershipType->id,
                'sabha_id' => $sabhaB->id,
                'pradeshiya_sabha_id' => $sabhaB->id,
                'member_id' => 'FORGED-MEMBER-ID',
                'user_id' => 999999,
                'role' => User::ROLE_SUPER_ADMIN,
                'status' => 'SUSPENDED',
                'registration_date' => '2000-01-01',
            ])
            ->assertRedirect();

        $member = Member::query()->where('email', 'staff-member@example.test')->firstOrFail();
        $user = $member->user;
        $this->assertNotNull($user);
        $this->assertSame($sabhaA->id, (int) $member->pradeshiya_sabha_id);
        $this->assertSame($sabhaA->id, (int) $user->pradeshiya_sabha_id);
        $this->assertNotSame('FORGED-MEMBER-ID', $member->member_id);
        $this->assertSame(User::ROLE_MEMBER, $user->role);
        $this->assertSame('ACTIVE', $member->status);
        $this->assertSame(today()->toDateString(), $member->registration_date->toDateString());
        $this->assertTrue(Hash::check('member-initial-password', $user->password));
        $this->assertDatabaseHas('membership_payments', [
            'member_id' => $member->id,
            'payment_type' => 'REGISTRATION',
            'amount' => '123.45',
            'status' => 'PAID',
        ]);

        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', [
            'email' => 'staff-member@example.test',
            'password' => 'member-initial-password',
        ])->assertRedirect(route('member.dashboard'));
        $this->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee($member->member_id)
            ->assertSee('Member Dashboard');
    }

    public function test_member_portal_shows_only_own_activity_and_blocks_admin_mutations(): void
    {
        [$sabhaA, $adminA] = $this->sabhaAdmin('PORTAL-A');
        [$sabhaB, $adminB] = $this->sabhaAdmin('PORTAL-B');
        $memberA = $this->member($sabhaA, 'Portal Member A');
        $memberB = $this->member($sabhaB, 'Portal Member B');
        $bookA = $this->book($sabhaA, 'Portal Sabha A Book');
        $bookB = $this->book($sabhaB, 'Portal Sabha B Book');
        $copyA = BookCopy::create([
            'book_id' => $bookA->id,
            'pradeshiya_sabha_id' => $sabhaA->id,
            'accession_number' => 'PORTAL-A-COPY',
            'status' => 'ISSUED',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);
        $copyB = BookCopy::create([
            'book_id' => $bookB->id,
            'pradeshiya_sabha_id' => $sabhaB->id,
            'accession_number' => 'PORTAL-B-COPY',
            'status' => 'ISSUED',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);
        $borrowingA = Borrowing::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'member_id' => $memberA->id,
            'book_id' => $bookA->id,
            'book_copy_id' => $copyA->id,
            'issue_date' => today()->subDays(7),
            'due_date' => today()->addDays(7),
            'status' => 'ISSUED',
            'renewal_count' => 0,
        ]);
        $borrowingB = Borrowing::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'book_id' => $bookB->id,
            'book_copy_id' => $copyB->id,
            'issue_date' => today()->subDays(8),
            'due_date' => today()->addDays(6),
            'status' => 'ISSUED',
            'renewal_count' => 1,
        ]);
        $fineA = Fine::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'member_id' => $memberA->id,
            'borrowing_id' => $borrowingA->id,
            'amount' => '4.25',
            'issued_date' => today(),
            'fine_type' => 'OVERDUE',
            'status' => 'UNPAID',
        ]);
        $fineB = Fine::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'amount' => '9.50',
            'issued_date' => today(),
            'fine_type' => 'OVERDUE',
            'status' => 'UNPAID',
        ]);
        $paymentA = Payment::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'member_id' => $memberA->id,
            'borrowing_id' => $borrowingA->id,
            'payment_type' => 'OVERDUE_FINE',
            'amount' => '4.25',
            'payment_method' => 'cash',
            'payment_date' => today(),
            'status' => 'PAID',
        ]);
        $paymentB = Payment::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'member_id' => $memberB->id,
            'borrowing_id' => $borrowingB->id,
            'payment_type' => 'OVERDUE_FINE',
            'amount' => '9.50',
            'payment_method' => 'cash',
            'payment_date' => today(),
            'status' => 'PAID',
        ]);

        $this->actingAs($memberA->user)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee($memberA->member_id)
            ->assertSee($bookA->title)
            ->assertDontSee($memberB->full_name)
            ->assertDontSee($bookB->title)
            ->assertSee('member-portal-nav');
        $this->get(route('member.borrowings', ['member_id' => $memberB->id]))
            ->assertOk()
            ->assertSee($bookA->title)
            ->assertDontSee($bookB->title);
        $this->get(route('member.fines', ['member_id' => $memberB->id]))
            ->assertOk()
            ->assertSee('4.25')
            ->assertDontSee('9.50');
        $this->get(route('member.payments', ['member_id' => $memberB->id]))
            ->assertOk()
            ->assertSee('4.25')
            ->assertDontSee('9.50');
        $this->get(route('member.profile', ['member_id' => $memberB->id]))
            ->assertOk()
            ->assertSee($memberA->member_id)
            ->assertDontSee($memberB->member_id);
        $this->get(route('member.books.search', ['sabha_id' => $sabhaB->id]))
            ->assertOk()
            ->assertSee($bookA->title)
            ->assertDontSee($bookB->title);

        $this->get(route('admin.dashboard'))->assertRedirect(route('member.dashboard'));
        $this->get(route('admin.members.show', $memberB))->assertNotFound();
        $this->post(route('admin.books.store'), [])->assertRedirect(route('member.dashboard'));
        $this->post(route('admin.authors.store'), ['name' => 'No Member Author'])->assertRedirect(route('member.dashboard'));
        $this->post(route('admin.publishers.store'), ['name' => 'No Member Publisher'])->assertRedirect(route('member.dashboard'));
        $this->post(route('admin.categories.store'), [])->assertRedirect(route('member.dashboard'));
        $this->put(route('admin.settings.update'), [])->assertRedirect(route('member.dashboard'));
    }

    private function sabhaAdmin(string $code): array
    {
        $sabha = PradeshiyaSabha::create(['code' => $code, 'name' => 'Member Portal '.$code]);
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

        return [$sabha, $admin];
    }

    private function configureSettings(PradeshiyaSabha $sabha): void
    {
        foreach ([
            'registration_fee' => '123.45',
            'monthly_membership_fee' => '50.00',
            'late_renewal_fee' => '10.00',
            'late_renewal_penalty' => '5.00',
            'overdue_fine_amount' => '2.50',
            'membership_duration_months' => '6',
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
    }

    private function member(PradeshiyaSabha $sabha, string $name): Member
    {
        $type = MembershipType::query()->firstOrCreate(
            ['name' => 'Individual'],
            ['max_books' => 2, 'description' => 'Individual']
        );
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        return Member::create([
            'user_id' => $user->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'membership_type_id' => $type->id,
            'full_name' => $name,
            'email' => $user->email,
            'nic' => 'NIC-'.$user->id,
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
        return Book::create([
            'book_id' => strtoupper(substr(md5($title.$sabha->id), 0, 12)),
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => $title,
            'author_id' => Author::create(['name' => $title.' Author', 'pradeshiya_sabha_id' => $sabha->id])->id,
            'publisher_id' => Publisher::create(['name' => $title.' Publisher', 'pradeshiya_sabha_id' => $sabha->id])->id,
            'category_id' => Category::create([
                'code' => 'PH4-'.strtoupper(substr(md5($title.$sabha->id), 0, 6)),
                'name' => $title.' Category',
                'pradeshiya_sabha_id' => $sabha->id,
            ])->id,
            'ddc_number' => '600.1',
            'status' => 'available',
        ]);
    }
}