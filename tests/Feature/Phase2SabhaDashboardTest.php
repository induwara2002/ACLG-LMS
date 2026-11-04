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
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2SabhaDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_and_alerts_show_only_the_authenticated_sabha(): void
    {
        [$sabhaA, $adminA, $dataA] = $this->tenantWithActivity('PHA');
        [$sabhaB, , $dataB] = $this->tenantWithActivity('PHB');
        PradeshiyaSabhaSetting::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'key' => 'library_name',
            'value' => 'Harbor PHA Library',
        ]);
        PradeshiyaSabhaSetting::create([
            'pradeshiya_sabha_id' => $sabhaB->id,
            'key' => 'library_name',
            'value' => 'Harbor PHB Library',
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.dashboard', ['sabha_id' => $sabhaB->id]))
            ->assertOk()
            ->assertSee('Welcome back, Library Admin')
            ->assertSee('data-sri-lanka-clock', false)
            ->assertSee('Total Members')
            ->assertSee('Currently Borrowed')
            ->assertSee('Monthly Book Issues')
            ->assertSee('Books by Category')
            ->assertSee('3 days overdue')
            ->assertSee($dataA['member']->full_name)
            ->assertSee($dataA['overdueBook']->title)
            ->assertSee('Recent Activity')
            ->assertSee('Quick Actions')
            ->assertSee('Library Administrator')
            ->assertSee('Account password')
            ->assertSee('Logout')
            ->assertSee('data-ps-sidebar-toggle', false)
            ->assertSee(route('admin.members.create'), false)
            ->assertSee(route('admin.books.create'), false)
            ->assertSee(route('admin.issue-books'), false)
            ->assertSee(route('admin.returns.index'), false)
            ->assertSee(route('admin.books.index'), false)
            ->assertSee(route('admin.members.index'), false)
            ->assertSee(route('admin.borrowings.index', ['status' => 'ISSUED']), false)
            ->assertSee(route('admin.borrowings.index', ['status' => 'OVERDUE']), false)
            ->assertSee(route('admin.reports.index'), false)
            ->assertSee('7.00')
            ->assertSee($dataA['lostBook']->book->title)
            ->assertSee($dataA['damageRecord']->book->title)
            ->assertDontSee($dataB['member']->full_name)
            ->assertDontSee($dataB['overdueBook']->title)
            ->assertSee('PHA Overdue Book Category')
            ->assertDontSee('PHB Overdue Book Category')
            ->assertDontSee($dataB['lostBookTitle'])
            ->assertDontSee($dataB['damageBookTitle'])
            ->assertSee('ps-admin-sidebar')
            ->assertSee('Library Settings')
            ->assertDontSee('Publishers')
            ->assertViewHas('memberStats', fn ($stats) => $stats['total'] === 1)
            ->assertViewHas('bookStats', fn ($stats) => $stats['titles'] === 4 && $stats['available'] === 1)
            ->assertViewHas('borrowingStats', fn ($stats) => $stats['issued'] === 1 && $stats['overdue'] === 1)
            ->assertViewHas('categoryStats', fn ($stats) => $stats['total'] === 4)
            ->assertViewHas('dashboardTrends', fn ($trends) => $trends['borrowed']['current'] === 1
                && $trends['overdue']['current'] === 1)
            ->assertViewHas('alerts', fn ($alerts) => $alerts['unpaidFines'] === 1
                && $alerts['lostBooks'] === 1
                && $alerts['damagedBooks'] === 1
                && $alerts['overdueRecords']->count() === 1
                && $alerts['membershipRenewals']->count() === 1
                && $alerts['unpaidFineRecords']->count() === 1);

        $this->get(route('admin.members.index'))->assertOk()->assertSee($dataA['member']->full_name)->assertDontSee($dataB['member']->full_name);
        $this->get(route('admin.books.index'))->assertOk()->assertSee($dataA['overdueBook']->title)->assertDontSee($dataB['overdueBook']->title);
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('Harbor PHA Library')->assertDontSee('Harbor PHB Library');
        $this->get(route('admin.sabhas.dashboard', $sabhaB))->assertRedirect(route('admin.dashboard'));
    }

    public function test_dashboard_has_empty_alert_states_when_tenant_has_no_alerts(): void
    {
        [$sabha, $admin] = $this->tenant('EMP');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No alerts at this time.')
            ->assertSee('No overdue books.')
            ->assertSee('No pending fines.')
            ->assertViewHas('memberStats', fn ($stats) => $stats['total'] === 0);
    }

    public function test_own_settings_accept_library_details_but_ignore_forged_tenant_ids(): void
    {
        [$sabhaA, $adminA] = $this->tenant('STA');
        [$sabhaB] = $this->tenant('STB');

        $this->actingAs($adminA)->get(route('admin.settings.index'))->assertOk()->assertSee($sabhaA->name);
        $this->put(route('admin.settings.update'), $this->settingsPayload('Harbor STA Library'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Sabha settings saved. Configuration complete.');
        $this->assertSame('Harbor STA Library', $sabhaA->setting('library_name'));
        $this->assertSame('Monday to Friday, 9:00 to 17:00', $sabhaA->setting('opening_hours'));

        $this->put(route('admin.settings.update'), $this->settingsPayload('Harbor STB Library') + ['sabha_id' => $sabhaB->id])
            ->assertSessionHasErrors('settings');
        $this->assertNull($sabhaB->setting('library_name'));

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), $this->settingsPayload('Forbidden Library'))
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame('Harbor STA Library', $sabhaA->fresh()->setting('library_name'));
    }

    public function test_private_logo_display_and_password_change_reject_incorrect_current_password(): void
    {
        Storage::fake('local');
        [$sabhaA, $adminA] = $this->tenant('LOGA', 'current-admin-password');
        [$sabhaB, $adminB] = $this->tenant('LOGB');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->post(route('admin.sabhas.logo.upload', $sabhaA), ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertRedirect();

        $this->actingAs($adminA)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('sabhas.logo', $sabhaA));
        $this->get(route('sabhas.logo', $sabhaA))->assertOk();
        $this->get(route('sabhas.logo', $sabhaB))->assertNotFound();
        $this->actingAs($adminB)->get(route('sabhas.logo', $sabhaA))->assertNotFound();

        $this->actingAs($adminA)
            ->put(route('account.password.update'), [
                'current_password' => 'incorrect-current-password',
                'password' => 'replacement-admin-password',
                'password_confirmation' => 'replacement-admin-password',
            ])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('current-admin-password', $adminA->fresh()->password));

        $this->put(route('account.password.update'), [
            'current_password' => 'current-admin-password',
            'password' => 'replacement-admin-password',
            'password_confirmation' => 'replacement-admin-password',
        ])->assertSessionHas('success', 'Password updated successfully.');
        $this->assertTrue(Hash::check('replacement-admin-password', $adminA->fresh()->password));
    }

    private function tenant(string $code, string $password = 'current-admin-password'): array
    {
        $sabha = PradeshiyaSabha::create(['code' => $code, 'name' => 'Harbor '.$code.' Sabha']);
        $admin = User::factory()->create([
            'password' => Hash::make($password),
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

    private function tenantWithActivity(string $code): array
    {
        [$sabha, $admin] = $this->tenant($code);
        $membershipType = MembershipType::query()->firstOrCreate(
            ['name' => 'Individual'],
            ['max_books' => 2, 'description' => 'Individual membership']
        );
        $memberUser = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $member = Member::create([
            'user_id' => $memberUser->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'membership_type_id' => $membershipType->id,
            'full_name' => 'Member '.$code,
            'email' => $memberUser->email,
            'nic' => 'NIC-'.$code,
            'phone' => '0771234567',
            'address' => 'Test address',
            'registration_fee' => '0.00',
            'registration_date' => today(),
            'membership_start_date' => today(),
            'next_renewal_date' => today()->addDays(7),
            'expiry_date' => today()->addDays(7),
            'status' => 'ACTIVE',
        ]);

        $overdueBook = $this->book($sabha, $code, $code.' Overdue Book', 'ISSUED');
        $borrowing = Borrowing::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'book_id' => $overdueBook->id,
            'book_copy_id' => $overdueBook->copies()->firstOrFail()->id,
            'issue_date' => today()->subDays(20),
            'due_date' => today()->subDays(3),
            'status' => 'ISSUED',
            'renewal_count' => 0,
        ]);
        $fine = Fine::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'amount' => '7.00',
            'issued_date' => today(),
            'fine_type' => 'OVERDUE',
            'status' => 'UNPAID',
        ]);
        $this->book($sabha, $code, $code.' Available Book', 'AVAILABLE');
        $lostBook = $this->book($sabha, $code, $code.' Lost Book', 'LOST');
        $lostRecord = LostBook::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'book_id' => $lostBook->id,
            'book_copy_id' => $lostBook->copies()->firstOrFail()->id,
            'lost_date' => today(),
            'replacement_cost' => '10.00',
            'compensation_amount' => '10.00',
            'status' => 'REPORTED',
            'payment_status' => 'UNPAID',
        ]);
        $damageBook = $this->book($sabha, $code, $code.' Damaged Book', 'DAMAGED');
        $damageRecord = DamageRecord::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'book_id' => $damageBook->id,
            'book_copy_id' => $damageBook->copies()->firstOrFail()->id,
            'damage_type' => 'MAJOR',
            'damage_date' => today(),
            'description' => 'Test damage',
            'repair_cost' => '8.00',
            'compensation_amount' => '8.00',
            'status' => 'REPORTED',
            'payment_status' => 'UNPAID',
        ]);

        return [$sabha, $admin, [
            'member' => $member,
            'overdueBook' => $overdueBook,
            'fine' => $fine,
            'lostBook' => $lostRecord,
            'lostBookTitle' => $lostBook->title,
            'damageRecord' => $damageRecord,
            'damageBookTitle' => $damageBook->title,
        ]];
    }

    private function book(PradeshiyaSabha $sabha, string $code, string $title, string $copyStatus): Book
    {
        $author = Author::create(['name' => $title.' Author', 'pradeshiya_sabha_id' => $sabha->id]);
        $publisher = Publisher::create(['name' => $title.' Publisher', 'pradeshiya_sabha_id' => $sabha->id]);
        $category = Category::create([
            'code' => $code.'-'.strtoupper(substr(md5($title), 0, 6)),
            'name' => $title.' Category',
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $book = Book::create([
            'book_id' => $code.'-'.strtoupper(substr(md5($title), 0, 8)),
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => $title,
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '600.1',
            'status' => 'ACTIVE',
        ]);
        BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'accession_number' => $code.'-COPY-'.strtoupper(substr(md5($title), 0, 8)),
            'status' => $copyStatus,
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        return $book;
    }

    private function settingsPayload(string $libraryName): array
    {
        return [
            'library_name' => $libraryName,
            'library_address' => '1 Harbor Road',
            'contact_number' => '0111234567',
            'library_email' => strtolower(str_replace(' ', '.', $libraryName)).'@example.test',
            'opening_hours' => 'Monday to Friday, 9:00 to 17:00',
            'registration_fee' => '125.00',
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
        ];
    }
}