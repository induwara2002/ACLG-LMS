<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuditLog;
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
use App\Models\User;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\Publisher;
use App\Services\BorrowingService;
use App\Services\MembershipService;
use App\Services\PaymentService;
use Database\Seeders\PradeshiyaSabhaAdminSeeder;
use Database\Seeders\PradeshiyaSabhaSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class Phase14MultiSabhaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('library_management_test', DB::connection()->getDatabaseName());
    }

    public function test_unassigned_user_does_not_satisfy_the_pradeshiya_sabha_admin_role(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
            'pradeshiya_sabha_id' => null,
        ]);

        $this->assertFalse($user->isPradeshiyaSabhaAdmin());
        $this->assertFalse($user->hasRole(User::ROLE_PRADESHIYA_SABHA_ADMIN));
    }

    public function test_seeder_creates_exact_official_codes_idempotently_and_preserves_other_records(): void
    {
        $existingRecord = PradeshiyaSabha::create([
            'code' => 'EXT',
            'name' => 'Existing Local Authority',
        ]);

        $this->seed(PradeshiyaSabhaSeeder::class);
        $this->seed(PradeshiyaSabhaSeeder::class);

        $expectedCodes = ['BAL', 'AMB', 'WEL', 'KAR', 'BEN', 'ELP', 'NEL', 'THA', 'NAG', 'NIY', 'BAD', 'YAK', 'RAJ', 'AKM', 'BOP', 'IMA', 'HAB', 'GAL-MC', 'HIK-MC', 'AMB-UC'];
        sort($expectedCodes);
        $sabhas = PradeshiyaSabha::query()->whereIn('code', $expectedCodes)->orderBy('code')->get();

        $this->assertSame(20, $sabhas->count());
        $this->assertSame($expectedCodes, $sabhas->pluck('code')->all());
        $expectedNames = [
            'AKM' => 'Akmeemana Pradeshiya Sabha',
            'AMB' => 'Ambalangoda Pradeshiya Sabha',
            'AMB-UC' => 'Ambalangoda Urban Council',
            'BAD' => 'Baddegama Pradeshiya Sabha',
            'BAL' => 'Balapitiya Pradeshiya Sabha',
            'BEN' => 'Bentota Pradeshiya Sabha',
            'BOP' => 'Bope Poddala Pradeshiya Sabha',
            'ELP' => 'Elpitiya Pradeshiya Sabha',
            'GAL-MC' => 'Galle Municipal Council',
            'HAB' => 'Habaraduwa Pradeshiya Sabha',
            'HIK-MC' => 'Hikkaduwa Municipal Council',
            'IMA' => 'Imaduwa Pradeshiya Sabha',
            'KAR' => 'Karandeniya Pradeshiya Sabha',
            'NAG' => 'Nagoda Pradeshiya Sabha',
            'NEL' => 'Neluwa Pradeshiya Sabha',
            'NIY' => 'Niyagama Pradeshiya Sabha',
            'RAJ' => 'Rajgama Pradeshiya Sabha',
            'THA' => 'Thawalama Pradeshiya Sabha',
            'WEL' => 'Welivitiya Divithura Pradeshiya Sabha',
            'YAK' => 'Yakkalamulla Pradeshiya Sabha',
        ];
        $this->assertSame($expectedNames, $sabhas->keyBy('code')->map->name->all());
        $this->assertSame(21, PradeshiyaSabha::count());
        $this->assertModelExists($existingRecord);
        foreach (['GAL-MC', 'HIK-MC', 'AMB-UC'] as $code) {
            $sabha = $sabhas->firstWhere('code', $code);
            $this->assertSame(12, $sabha->settings()->count());
        }
    }

    public function test_added_galle_councils_are_visible_to_super_admin_and_isolated_for_their_admins(): void
    {
        $this->seed(PradeshiyaSabhaSeeder::class);
        $sabhas = PradeshiyaSabha::query()->whereIn('code', ['GAL-MC', 'HIK-MC', 'AMB-UC'])->orderBy('code')->get();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $members = [];
        $books = [];
        $admins = [];

        foreach ($sabhas as $sabha) {
            $members[$sabha->code] = $this->createMember($sabha);
            $books[$sabha->code] = $this->createBook($sabha, $sabha->code);
            $admins[$sabha->code] = $this->createSabhaAdmin($sabha);
        }

        $this->actingAs($superAdmin)
            ->get(route('admin.sabhas.index'))
            ->assertOk()
            ->assertSee('Galle Municipal Council')
            ->assertSee('Hikkaduwa Municipal Council')
            ->assertSee('Ambalangoda Urban Council');

        foreach ($sabhas as $sabha) {
            $otherCodes = $sabhas->where('id', '!=', $sabha->id)->pluck('code');
            $response = $this->actingAs($admins[$sabha->code])->get(route('admin.members.index'))->assertOk();
            $response->assertSee($members[$sabha->code]->full_name);
            foreach ($otherCodes as $otherCode) {
                $response->assertDontSee($members[$otherCode]->full_name);
                $bookResponse = $this->get(route('admin.books.show', $books[$otherCode]))->assertNotFound();
                $bookResponse
                    ->assertDontSee($books[$otherCode]->title)
                    ->assertDontSee($members[$otherCode]->full_name)
                    ->assertDontSee(BookCopy::withoutGlobalScopes()->where('book_id', $books[$otherCode]->id)->value('accession_number'));
            }
        }
    }

    public function test_admin_seeder_preserves_existing_account_and_assignment(): void
    {
        $this->seed(PradeshiyaSabhaSeeder::class);
        $sabha = PradeshiyaSabha::query()->where('code', 'BAL')->firstOrFail();
        $existingPassword = Hash::make('existing-test-only-password');
        $existingAdmin = User::factory()->create([
            'name' => 'Existing Balapitiya Admin',
            'email' => 'existing.balapitiya@library.local',
            'password' => $existingPassword,
            'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $assignment = PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $existingAdmin->id,
            'is_active' => true,
        ]);

        config(['library.pradeshiya_sabha_admin_passwords' => array_fill_keys(
            ['BAL', 'AMB', 'WEL', 'KAR', 'BEN', 'ELP', 'NEL', 'THA', 'NAG', 'NIY', 'BAD', 'YAK', 'RAJ', 'AKM', 'BOP', 'IMA', 'HAB'],
            'test-only-password-long-enough'
        )]);
        config(['library.pradeshiya_sabha_admin_passwords.BAL' => null]);

        $this->seed(PradeshiyaSabhaAdminSeeder::class);

        $this->assertSame($existingPassword, $existingAdmin->fresh()->password);
        $this->assertSame($existingAdmin->id, $assignment->fresh()->user_id);
        $this->assertSame(17, PradeshiyaSabhaAdminAssignment::query()->where('is_active', true)->count());
        $this->assertSame(17, User::query()->where('role', User::ROLE_PRADESHIYA_SABHA_ADMIN)->where('is_active', true)->count());
    }

    public function test_admin_seeder_preflights_credentials_before_creating_any_account(): void
    {
        $this->seed(PradeshiyaSabhaSeeder::class);
        $passwords = array_fill_keys(
            ['BAL', 'AMB', 'WEL', 'KAR', 'BEN', 'ELP', 'NEL', 'THA', 'NAG', 'NIY', 'BAD', 'YAK', 'RAJ', 'AKM', 'BOP', 'IMA', 'HAB'],
            'test-only-password-long-enough'
        );
        unset($passwords['NAG']);
        config(['library.pradeshiya_sabha_admin_passwords' => $passwords]);

        try {
            $this->seed(PradeshiyaSabhaAdminSeeder::class);
            $this->fail('Missing credentials must stop provisioning.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('NAG', $exception->getMessage());
        }

        $this->assertSame(0, PradeshiyaSabhaAdminAssignment::count());
        $this->assertSame(0, User::query()->where('role', User::ROLE_PRADESHIYA_SABHA_ADMIN)->count());
    }

    public function test_main_admin_services_use_the_member_sabhas_settings_and_reject_cross_sabha_links(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'TSTA', 'name' => 'Test Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'TSTB', 'name' => 'Test Sabha B']);
        $memberA = $this->createMember($sabhaA);
        $memberB = $this->createMember($sabhaB);
        $bookA = $this->createBook($sabhaA, 'A');
        $bookB = $this->createBook($sabhaB, 'B1');
        $bookBSecond = $this->createBook($sabhaB, 'B2');

        $this->setSettings($sabhaA, [
            'registration_fee' => '101.00', 'monthly_membership_fee' => '31.00',
            'late_renewal_fee' => '8.00', 'late_renewal_penalty' => '2.00',
            'membership_duration_months' => '2', 'individual_borrowing_limit' => '2',
            'family_borrowing_limit' => '4', 'issue_period_days' => '10',
            'maximum_renewals' => '1', 'overdue_fine_amount' => '3.00',
        ]);
        $this->setSettings($sabhaB, [
            'registration_fee' => '42.00', 'monthly_membership_fee' => '63.00',
            'late_renewal_fee' => '10.00', 'late_renewal_penalty' => '4.00',
            'membership_duration_months' => '4', 'individual_borrowing_limit' => '1',
            'family_borrowing_limit' => '3', 'issue_period_days' => '7',
            'maximum_renewals' => '2', 'overdue_fine_amount' => '7.25',
        ]);

        $mainAdmin = User::factory()->create(['role' => User::ROLE_MAIN_ADMIN, 'is_active' => true]);
        $this->actingAs($mainAdmin);

        $registration = app(PaymentService::class)->recordMembership($memberB, 'REGISTRATION');
        $this->assertSame('42.00', $registration->amount);
        $this->assertSame($sabhaB->id, (int) $registration->pradeshiya_sabha_id);

        app(MembershipService::class)->renew($memberB);
        $memberB->refresh();
        $this->assertSame('63.00', $memberB->membershipPayments()->latest('id')->firstOrFail()->amount);
        $this->assertSame(today()->addMonths(4)->toDateString(), $memberB->expiry_date->toDateString());

        $borrowing = app(BorrowingService::class)->issue($memberB, $bookB);
        $this->assertSame($sabhaB->id, (int) $borrowing->pradeshiya_sabha_id);
        $this->assertSame(today()->addDays(7)->toDateString(), $borrowing->due_date->toDateString());

        $fine = app(PaymentService::class)->createFine($memberB, '999.00', 'OVERDUE', $borrowing->id);
        $this->assertSame('7.25', $fine->amount);
        $this->assertSame($sabhaB->id, (int) $fine->pradeshiya_sabha_id);

        try {
            app(BorrowingService::class)->issue($memberA, $bookBSecond);
            $this->fail('A member must not borrow from another Sabha.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('same Pradeshiya Sabha', $exception->getMessage());
        }

        try {
            app(BorrowingService::class)->issue($memberB, $bookBSecond);
            $this->fail('The Sabha borrowing limit must be enforced.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('borrowing limit', $exception->getMessage());
        }

        try {
            app(PaymentService::class)->createFine($memberA, '20.00', 'OVERDUE', $borrowing->id);
            $this->fail('A fine must not reference another Sabha\'s borrowing.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('fines', 1);
        }

        $paidPayment = app(PaymentService::class)->payFine($fine);
        $this->assertSame($sabhaB->id, (int) $paidPayment->pradeshiya_sabha_id);
    }

    public function test_sabha_admin_is_isolated_across_models_routes_reports_search_and_dashboard(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'IDRA', 'name' => 'IDOR Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'IDRB', 'name' => 'IDOR Sabha B']);
        $adminA = $this->createSabhaAdmin($sabhaA);
        $adminB = $this->createSabhaAdmin($sabhaB);
        $memberA = $this->createMember($sabhaA);
        $memberB = $this->createMember($sabhaB);
        $bookA = $this->createBook($sabhaA, 'IDRA');
        $issueBookA = $this->createBook($sabhaA, 'IDRA-ISSUE');
        $bookB = $this->createBook($sabhaB, 'IDRB');
        $authorA = Author::create(['name' => 'Tenant A Author', 'pradeshiya_sabha_id' => $sabhaA->id]);
        $authorB = Author::create(['name' => 'Tenant B Author', 'pradeshiya_sabha_id' => $sabhaB->id]);
        $publisherA = Publisher::create(['name' => 'Tenant A Publisher', 'pradeshiya_sabha_id' => $sabhaA->id]);
        $publisherB = Publisher::create(['name' => 'Tenant B Publisher', 'pradeshiya_sabha_id' => $sabhaB->id]);
        $categoryA = Category::create(['code' => 'IDRA-CAT', 'name' => 'Tenant A Category', 'pradeshiya_sabha_id' => $sabhaA->id]);
        $categoryB = Category::create(['code' => 'IDRB-CAT', 'name' => 'Tenant B Category', 'pradeshiya_sabha_id' => $sabhaB->id]);
        $this->setSettings($sabhaA, ['individual_borrowing_limit' => '2', 'issue_period_days' => '9']);
        $borrowingA = $this->createBorrowing($memberA, $bookA);
        $borrowingB = $this->createBorrowing($memberB, $bookB);
        $paymentA = $this->createPayment($sabhaA, $memberA, $borrowingA);
        $paymentB = $this->createPayment($sabhaB, $memberB, $borrowingB);
        $fineA = $this->createFine($sabhaA, $memberA, $borrowingA);
        $fineB = $this->createFine($sabhaB, $memberB, $borrowingB);
        $lostA = $this->createLostBook($sabhaA, $memberA, $borrowingA, $bookA);
        $lostB = $this->createLostBook($sabhaB, $memberB, $borrowingB, $bookB);
        $damageA = $this->createDamageRecord($sabhaA, $memberA, $borrowingA, $bookA);
        $damageB = $this->createDamageRecord($sabhaB, $memberB, $borrowingB, $bookB);
        AuditLog::create(['user_id' => $adminA->id, 'pradeshiya_sabha_id' => $sabhaA->id, 'action' => 'tenant-a']);
        AuditLog::create(['user_id' => $adminB->id, 'pradeshiya_sabha_id' => $sabhaB->id, 'action' => 'tenant-b']);

        $this->actingAs($adminA);

        $this->assertSame([$adminA->id, $memberA->user_id], User::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame([$memberA->id], Member::query()->pluck('id')->all());
        $this->assertSame([$authorA->id], Author::query()->pluck('id')->all());
        $this->assertSame([$publisherA->id], Publisher::query()->pluck('id')->all());
        $visibleCategoryIds = Category::query()->pluck('id')->all();
        $this->assertContains($categoryA->id, $visibleCategoryIds);
        $this->assertNotContains($categoryB->id, $visibleCategoryIds);
        $this->assertSame(
            $sabhaA->books()->orderBy('id')->pluck('id')->all(),
            Book::query()->orderBy('id')->pluck('id')->all()
        );
        $this->assertSame([
            $bookA->copies()->firstOrFail()->id,
            $issueBookA->copies()->firstOrFail()->id,
        ], BookCopy::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame([$borrowingA->id], Borrowing::query()->pluck('id')->all());
        $this->assertSame([$paymentA->id], Payment::query()->pluck('id')->all());
        $this->assertSame([$fineA->id], Fine::query()->pluck('id')->all());
        $this->assertSame([$lostA->id], LostBook::query()->pluck('id')->all());
        $this->assertSame([$damageA->id], DamageRecord::query()->pluck('id')->all());
        $this->assertSame(['tenant-a'], AuditLog::query()->orderBy('action')->pluck('action')->all());

        $this->get(route('admin.members.show', $memberA))->assertOk();
        $this->post(route('admin.borrowings.store'), ['member_id' => $memberA->id, 'book_id' => $issueBookA->id])
            ->assertRedirect(route('admin.borrowings.index'));
        $issuedForOwnSabha = Borrowing::query()->where('book_id', $issueBookA->id)->firstOrFail();
        $this->assertSame($sabhaA->id, (int) $issuedForOwnSabha->pradeshiya_sabha_id);
        $this->assertSame(today()->addDays(9)->toDateString(), $issuedForOwnSabha->due_date->toDateString());
        $this->assertSame('ISSUED', $issueBookA->copies()->firstOrFail()->status);
        $this->get(route('admin.members.show', $memberB))->assertNotFound();
        $bookResponse = $this->get(route('admin.books.show', $bookB))->assertNotFound();
        $bookResponse
            ->assertDontSee($bookB->title)
            ->assertDontSee($memberB->full_name)
            ->assertDontSee(BookCopy::withoutGlobalScopes()->where('book_id', $bookB->id)->value('accession_number'));
        $this->post(route('admin.borrowings.return', $borrowingB))->assertNotFound();
        $this->post(route('admin.borrowings.store'), ['member_id' => $memberB->id, 'book_id' => $bookA->id])->assertNotFound();
        $this->post(route('admin.borrowings.store'), ['member_id' => $memberA->id, 'book_id' => $bookB->id])
            ->assertNotFound();
        $this->put(route('admin.members.update', $memberB), [])->assertNotFound();
        $this->delete(route('admin.members.destroy', $memberB))->assertNotFound();
        $this->get(route('admin.payments.show', $paymentB))->assertNotFound();
        $this->get(route('admin.fines.show', $fineB))->assertNotFound();
        $this->post(route('admin.lost-books.pay', $lostB))->assertNotFound();
        $this->post(route('admin.damaged-books.pay', $damageB))->assertNotFound();

        $search = app(\App\Services\SearchService::class)->searchMembers(['search' => $memberB->full_name]);
        $report = app(\App\Services\ReportService::class)->members(['search' => $memberB->full_name]);
        $this->assertSame(0, $search->total());
        $this->assertSame(0, $report->total());
        $this->get(route('admin.members.search', ['search' => $memberB->full_name, 'sabha_id' => $sabhaB->id]))
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->total() === 0);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('memberStats', fn ($stats) => $stats['total'] === 1)
            ->assertViewHas('bookStats', fn ($stats) => $stats['titles'] === 2 && $stats['copies'] === 2)
            ->assertViewHas('borrowingStats', fn ($stats) => $stats['issued'] === 2)
            ->assertViewHas('operationalStats', fn ($stats) => $stats['lostBooks'] === 1 && $stats['damagedBooks'] === 1)
            ->assertViewHas('financialStats', fn ($stats) => $stats['paymentCount'] === 1)
            ->assertSee('Register Member')
            ->assertSee('Issue Book')
            ->assertDontSee($memberB->full_name);
    }

    public function test_main_admin_can_aggregate_and_switch_to_one_sabha_dashboard(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'SWTA', 'name' => 'Switch Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'SWTB', 'name' => 'Switch Sabha B']);
        $memberA = $this->createMember($sabhaA);
        $memberB = $this->createMember($sabhaB);
        $mainAdmin = User::factory()->create(['role' => User::ROLE_MAIN_ADMIN, 'is_active' => true]);

        $this->actingAs($mainAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('sabhaTotals', fn ($totals) => $totals['total'] === 2 && $totals['active'] === 2)
            ->assertViewHas('memberStats', fn ($stats) => $stats['total'] === 2);

        $this->get(route('admin.members.search', ['sabha_id' => $sabhaA->id]))
            ->assertOk()
            ->assertViewHas('members', fn ($members) => $members->total() === 1
                && $members->first()->is($memberA)
                && ! $members->contains(fn ($member) => $member->is($memberB)));

        $this->get(route('admin.reports.show', ['report' => 'members', 'sabha_id' => $sabhaB->id]))
            ->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 1)
            ->assertViewHas('records', fn ($records) => $records->first()->is($memberB)
                && ! $records->contains(fn ($member) => $member->is($memberA)));

        $this->get(route('admin.sabhas.dashboard', $sabhaA))
            ->assertOk()
            ->assertViewHas('pradeshiyaSabha', fn ($selected) => $selected->is($sabhaA))
            ->assertViewHas('memberStats', fn ($stats) => $stats['total'] === 1)
            ->assertViewHas('sabhaTotals', fn ($totals) => $totals['total'] === 2);

        $this->get(route('admin.dashboard', ['sabha_id' => $sabhaA->id]))
            ->assertOk()
            ->assertViewHas('memberStats', fn ($stats) => $stats['total'] === 1);
    }

    public function test_main_admin_cannot_register_and_sabha_registration_ignores_submitted_sabha_id(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'MEMa', 'name' => 'Member Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'MEMb', 'name' => 'Member Sabha B']);
        $this->setSettings($sabhaA, [
            'registration_fee' => '11.00', 'monthly_membership_fee' => '10.00',
            'late_renewal_fee' => '5.00', 'late_renewal_penalty' => '1.00',
            'overdue_fine_amount' => '2.00', 'membership_duration_months' => '2',
            'individual_borrowing_limit' => '2', 'family_borrowing_limit' => '3',
            'issue_period_days' => '14', 'maximum_renewals' => '2',
            'currency' => 'LKR', 'timezone' => 'Asia/Colombo',
        ]);
        $this->setSettings($sabhaB, [
            'registration_fee' => '22.00', 'monthly_membership_fee' => '10.00',
            'late_renewal_fee' => '5.00', 'late_renewal_penalty' => '1.00',
            'overdue_fine_amount' => '2.00', 'membership_duration_months' => '3',
            'individual_borrowing_limit' => '2', 'family_borrowing_limit' => '3',
            'issue_period_days' => '14', 'maximum_renewals' => '2',
            'currency' => 'LKR', 'timezone' => 'Asia/Colombo',
        ]);
        $membershipType = MembershipType::query()->firstOrCreate(
            ['name' => 'Children'],
            ['max_books' => 2, 'description' => 'Children membership']
        );
        $mainAdmin = User::factory()->create(['role' => User::ROLE_MAIN_ADMIN, 'is_active' => true]);

        $this->actingAs($mainAdmin)->post(route('admin.members.store'), [
            'full_name' => 'Main Admin Created Member',
            'email' => 'main-admin-member@example.test',
            'nic' => 'PH14-MAIN-1',
            'phone' => '0771234001',
            'address' => 'Test address',
            'membership_type_id' => $membershipType->id,
            'sabha_id' => $sabhaB->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('members', ['email' => 'main-admin-member@example.test']);

        $sabhaAdmin = $this->createSabhaAdmin($sabhaA);
        $this->actingAs($sabhaAdmin)->post(route('admin.members.store'), [
            'full_name' => 'Sabha Admin Created Member',
            'email' => 'sabha-admin-member@example.test',
            'password' => 'phase14-member-password',
            'password_confirmation' => 'phase14-member-password',
            'nic' => 'PH14-SABHA-1',
            'phone' => '0771234002',
            'address' => 'Test address',
            'membership_type_id' => $membershipType->id,
            'sabha_id' => $sabhaB->id,
        ])->assertRedirect();

        $sabhaMember = Member::query()->where('email', 'sabha-admin-member@example.test')->firstOrFail();
        $this->assertSame($sabhaA->id, (int) $sabhaMember->pradeshiya_sabha_id);
        $this->assertSame('11.00', $sabhaMember->registration_fee);
    }

    public function test_main_admin_can_read_all_operational_data_but_cannot_mutate_it(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'READA', 'name' => 'Read Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'READB', 'name' => 'Read Sabha B']);
        $adminA = $this->createSabhaAdmin($sabhaA);
        $adminB = $this->createSabhaAdmin($sabhaB);
        $memberA = $this->createMember($sabhaA);
        $memberB = $this->createMember($sabhaB);
        $bookA = $this->createBook($sabhaA, 'READA');
        $bookB = $this->createBook($sabhaB, 'READB');
        $borrowing = $this->createBorrowing($memberA, $bookA);
        $fine = $this->createFine($sabhaA, $memberA, $borrowing);
        $payment = $this->createPayment($sabhaA, $memberA, $borrowing);
        $this->createLostBook($sabhaA, $memberA, $borrowing, $bookA);
        $this->createDamageRecord($sabhaA, $memberA, $borrowing, $bookA);
        $borrowing->update(['due_date' => today()->subDay()]);
        $mainAdmin = User::factory()->create(['role' => User::ROLE_MAIN_ADMIN, 'is_active' => true]);

        $this->actingAs($mainAdmin);

        $this->get(route('admin.sabhas.index'))->assertOk()->assertSee($sabhaA->name)->assertSee($sabhaB->name);
        $this->get(route('admin.members.index'))->assertOk()->assertSee($memberA->full_name)->assertSee($memberB->full_name)->assertDontSee('Register Member');
        $this->get(route('admin.members.show', $memberA))->assertOk()->assertDontSee('Renew Membership')->assertDontSee('Edit');
        $this->get(route('admin.books.index'))
            ->assertOk()
            ->assertSee($bookA->title)
            ->assertSee($bookB->title)
            ->assertDontSee('Add Book')
            ->assertDontSee(route('admin.books.edit', $bookA))
            ->assertDontSee(route('admin.books.edit', $bookB));
        $this->get(route('admin.books.show', $bookA))->assertOk()->assertDontSee('class="btn btn-primary">Edit</a>');
        $this->get(route('admin.book-copies.index'))->assertOk()->assertDontSee('Add Copy')->assertDontSee('Edit');
        $this->get(route('admin.borrowings.index'))->assertOk()->assertSee($memberA->full_name)->assertDontSee('btn-outline-success">Return</button>')->assertDontSee('Renew this book');
        $this->get(route('admin.returns.index'))->assertOk();
        $this->get(route('admin.renewals.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.fines.index'))->assertOk()->assertSee($fine->member->full_name)->assertDontSee('Create Fine')->assertDontSee('Waive');
        $this->get(route('admin.payments.index'))->assertOk()->assertSee($payment->member->full_name)->assertDontSee('Record fixed-fee payment');
        $this->get(route('admin.lost-damaged.index'))->assertOk()->assertDontSee('Report Lost')->assertDontSee('Report Damage');
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('sabhaTotals', fn ($totals) => $totals['total'] === 2 && $totals['activeAdmins'] === 2)
            ->assertViewHas('memberStats', fn ($stats) => $stats['total'] === 2)
            ->assertViewHas('financialStats', fn ($stats) => $stats['paymentCount'] === 1)
            ->assertDontSee('Issue Book')
            ->assertSee($adminA->name)
            ->assertSee($adminB->name);
        $this->assertSame('ISSUED', $borrowing->fresh()->status);

        $this->get(route('admin.members.create'))->assertForbidden();
        $this->get(route('admin.members.edit', $memberA))->assertForbidden();
        $this->post(route('admin.members.store'), [])->assertForbidden();
        $this->put(route('admin.members.update', $memberA), [])->assertForbidden();
        $this->delete(route('admin.members.destroy', $memberA))->assertForbidden();
        $this->get(route('admin.issue-books'))->assertForbidden();
        $this->post(route('admin.borrowings.store'), [])->assertForbidden();
        $this->post(route('admin.borrowings.return', $borrowing))->assertForbidden();
        $this->post(route('admin.borrowings.renew', $borrowing))->assertForbidden();
        $this->post(route('admin.fines.store'), [])->assertForbidden();
        $this->post(route('admin.fines.pay', $fine))->assertForbidden();
        $this->post(route('admin.fines.waive', $fine))->assertForbidden();
        $this->post(route('admin.payments.membership'), [])->assertForbidden();
        $this->post(route('admin.books.store'), [])->assertForbidden();
        $this->put(route('admin.books.update', $bookA), [])->assertForbidden();
        $this->delete(route('admin.books.destroy', $bookA))->assertForbidden();
        $this->post(route('admin.book-copies.store'), [])->assertForbidden();

        $this->actingAs($adminA)->post(route('admin.fines.waive', $fine))->assertRedirect();
        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'status' => 'WAIVED']);
    }

    public function test_member_dashboard_uses_its_sabhas_configured_borrowing_limit(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'LIMIT', 'name' => 'Limit Sabha']);
        $member = $this->createMember($sabha);
        $this->setSettings($sabha, ['individual_borrowing_limit' => '1']);

        $this->actingAs($member->user)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertViewHas('borrowingLimit', 1)
            ->assertViewHas('remainingAllowance', 1);
    }

    public function test_logo_upload_accepts_only_images_and_uses_a_safe_path(): void
    {
        Storage::fake('local');
        $sabha = PradeshiyaSabha::create(['code' => 'LOG', 'name' => 'Logo Sabha']);
        $mainAdmin = User::factory()->create(['role' => User::ROLE_MAIN_ADMIN, 'is_active' => true]);

        $this->actingAs($mainAdmin)
            ->post(route('admin.sabhas.logo.upload', $sabha), [
                'logo' => UploadedFile::fake()->image('../../unsafe-name.png'),
            ])
            ->assertRedirect();

        $sabha->refresh();
        $this->assertSame('pradeshiya-sabhas/'.$sabha->id.'/logo.png', $sabha->logo_path);
        Storage::disk('local')->assertExists($sabha->logo_path);

        $this->from(route('admin.sabhas.show', $sabha))
            ->post(route('admin.sabhas.logo.upload', $sabha), [
                'logo' => UploadedFile::fake()->create('executable.php', 5, 'application/x-php'),
            ])
            ->assertSessionHasErrors('logo');

        $this->actingAs($mainAdmin)
            ->delete(route('admin.sabhas.logo.remove', $sabha))
            ->assertRedirect();
        $this->assertNull($sabha->fresh()->logo_path);
        Storage::disk('local')->assertMissing('pradeshiya-sabhas/'.$sabha->id.'/logo.png');
    }

    public function test_sabha_admin_cannot_change_another_sabhas_logo_or_settings(): void
    {
        Storage::fake('local');
        $sabhaA = PradeshiyaSabha::create(['code' => 'AUTHA', 'name' => 'Authorization Sabha A']);
        $sabhaB = PradeshiyaSabha::create([
            'code' => 'AUTHB',
            'name' => 'Authorization Sabha B',
        ]);
        $sabhaB->update(['logo_path' => 'pradeshiya-sabhas/'.$sabhaB->id.'/logo.png']);
        Storage::disk('local')->put($sabhaB->logo_path, 'existing logo');
        $this->setSettings($sabhaB, ['registration_fee' => '88.00']);
        $adminA = $this->createSabhaAdmin($sabhaA);

        $this->actingAs($adminA)
            ->post(route('admin.sabhas.logo.upload', $sabhaB), [
                'logo' => UploadedFile::fake()->image('replacement.png'),
            ])
            ->assertRedirect(route('admin.dashboard'));
        $this->put(route('admin.settings.update'), ['sabha_id' => $sabhaB->id])
            ->assertSessionHasErrors('settings');

        $this->assertSame('pradeshiya-sabhas/'.$sabhaB->id.'/logo.png', $sabhaB->fresh()->logo_path);
        $this->assertSame('88.00', $sabhaB->setting('registration_fee'));
        Storage::disk('local')->assertExists('pradeshiya-sabhas/'.$sabhaB->id.'/logo.png');
    }

    public function test_books_and_physical_copies_are_isolated_to_their_sabha(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'COPY-A', 'name' => 'Copy Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'COPY-B', 'name' => 'Copy Sabha B']);
        $adminA = $this->createSabhaAdmin($sabhaA);
        $adminB = $this->createSabhaAdmin($sabhaB);
        $bookA = $this->createBook($sabhaA, 'COPY-A');
        $bookB = $this->createBook($sabhaB, 'COPY-B');
        $copyA = $bookA->copies()->firstOrFail();
        $copyB = $bookB->copies()->firstOrFail();

        $this->actingAs($adminA)
            ->get(route('admin.books.show', $bookA))
            ->assertOk()
            ->assertSee($bookA->title)
            ->assertSee($copyA->accession_number)
            ->assertDontSee($copyB->accession_number);

        $this->get(route('admin.book-copies.show', $copyB))->assertNotFound();

        $this->actingAs($adminB)
            ->get(route('admin.books.show', $bookB))
            ->assertOk()
            ->assertSee($bookB->title)
            ->assertSee($copyB->accession_number)
            ->assertDontSee($copyA->accession_number);
    }

    private function createMember(PradeshiyaSabha $sabha): Member
    {
        $membershipType = MembershipType::query()->firstOrCreate(
            ['name' => 'Individual'],
            ['max_books' => 2, 'description' => 'Individual membership']
        );
        $user = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $today = today();

        return Member::create([
            'user_id' => $user->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'membership_type_id' => $membershipType->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'nic' => 'PH14-'.$user->id,
            'phone' => '0771234567',
            'address' => 'Test address',
            'registration_fee' => '0.00',
            'registration_date' => $today,
            'membership_start_date' => $today,
            'next_renewal_date' => $today,
            'expiry_date' => $today->copy()->addMonth(),
            'status' => 'ACTIVE',
        ]);
    }

    private function createBook(PradeshiyaSabha $sabha, string $suffix): Book
    {
        $author = Author::create(['name' => 'Phase 14 Author '.$suffix]);
        $publisher = Publisher::create(['name' => 'Phase 14 Publisher '.$suffix]);
        $category = Category::create(['code' => 'P14'.uniqid(), 'name' => 'Phase 14 Category '.$suffix]);
        $book = Book::create([
            'book_id' => 'P14-'.uniqid(),
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => 'Phase 14 Book '.$suffix,
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '600.1',
            'status' => 'available',
        ]);

        BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'accession_number' => 'P14-COPY-'.uniqid(),
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        return $book;
    }

    private function createSabhaAdmin(PradeshiyaSabha $sabha): User
    {
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

        return $admin;
    }

    private function createBorrowing(Member $member, Book $book): Borrowing
    {
        $today = today();

        return Borrowing::create([
            'pradeshiya_sabha_id' => $member->pradeshiya_sabha_id,
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => $book->copies()->firstOrFail()->id,
            'issue_date' => $today,
            'due_date' => $today->copy()->addDays(14),
            'status' => 'ISSUED',
            'renewal_count' => 0,
        ]);
    }

    private function createPayment(PradeshiyaSabha $sabha, Member $member, Borrowing $borrowing): Payment
    {
        return Payment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'payment_type' => 'OVERDUE_FINE',
            'amount' => '5.00',
            'payment_method' => 'cash',
            'payment_date' => today(),
            'status' => 'PAID',
        ]);
    }

    private function createFine(PradeshiyaSabha $sabha, Member $member, Borrowing $borrowing): Fine
    {
        return Fine::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'amount' => '5.00',
            'issued_date' => today(),
            'fine_type' => 'OVERDUE',
            'status' => 'UNPAID',
        ]);
    }

    private function createLostBook(PradeshiyaSabha $sabha, Member $member, Borrowing $borrowing, Book $book): LostBook
    {
        return LostBook::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_id' => $book->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'lost_date' => today(),
            'replacement_cost' => '5.00',
            'compensation_amount' => '5.00',
            'status' => 'REPORTED',
            'payment_status' => 'UNPAID',
        ]);
    }

    private function createDamageRecord(PradeshiyaSabha $sabha, Member $member, Borrowing $borrowing, Book $book): DamageRecord
    {
        return DamageRecord::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'member_id' => $member->id,
            'borrowing_id' => $borrowing->id,
            'book_id' => $book->id,
            'book_copy_id' => $borrowing->book_copy_id,
            'damage_type' => 'MAJOR',
            'damage_date' => today(),
            'description' => 'Test damage',
            'repair_cost' => '5.00',
            'compensation_amount' => '5.00',
            'status' => 'REPORTED',
            'payment_status' => 'UNPAID',
        ]);
    }

    private function setSettings(PradeshiyaSabha $sabha, array $settings): void
    {
        foreach ($settings as $key => $value) {
            PradeshiyaSabhaSetting::query()->updateOrCreate(
                ['pradeshiya_sabha_id' => $sabha->id, 'key' => $key],
                ['value' => $value]
            );
        }
    }
}