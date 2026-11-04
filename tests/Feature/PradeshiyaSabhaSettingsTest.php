<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\Publisher;
use App\Models\User;
use App\Services\PradeshiyaSabhaSettingsService;
use Database\Seeders\MembershipTypeSeeder;
use Database\Seeders\PradeshiyaSabhaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PradeshiyaSabhaSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('library_management_test', DB::connection()->getDatabaseName());
        $this->seed(PradeshiyaSabhaSeeder::class);
        $this->seed(MembershipTypeSeeder::class);
    }

    public function test_main_admin_can_view_settings_for_any_sabha_and_incomplete_status_is_shown(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_MAIN_ADMIN, 'is_active' => true]);
        $first = PradeshiyaSabha::query()->where('code', 'AKM')->firstOrFail();
        $last = PradeshiyaSabha::query()->where('code', 'YAK')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.sabhas.show', $first))
            ->assertOk()
            ->assertSee($first->name)
            ->assertSee('Configuration incomplete')
            ->assertDontSee('Save Library Settings');
        $this->get(route('admin.sabhas.show', $last))
            ->assertOk()
            ->assertSee($last->name)
            ->assertSee('Independent library settings are managed by this Sabha');

        $status = app(PradeshiyaSabhaSettingsService::class)->configurationStatus($first);
        $this->assertFalse($status['complete']);
        $this->assertCount(12, $status['missing']);
    }

    public function test_super_admin_cannot_edit_settings_but_the_owning_sabha_admin_can(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'BAL')->firstOrFail();
        $beforeCount = PradeshiyaSabhaSetting::query()->where('pradeshiya_sabha_id', $sabha->id)->count();

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), $this->completeSettings())
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame($beforeCount, PradeshiyaSabhaSetting::query()->where('pradeshiya_sabha_id', $sabha->id)->count());

        $this->actingAs($this->createSabhaAdmin($sabha))
            ->put(route('admin.settings.update'), $this->completeSettings())
            ->assertRedirect()
            ->assertSessionHas('success', 'Sabha settings saved. Configuration complete.');

        $this->assertTrue(app(PradeshiyaSabhaSettingsService::class)->configurationStatus($sabha->fresh())['complete']);
        $this->assertSame($beforeCount, PradeshiyaSabhaSetting::query()->where('pradeshiya_sabha_id', $sabha->id)->count());
        $this->assertSame(1, PradeshiyaSabhaSetting::query()->where('pradeshiya_sabha_id', $sabha->id)->where('key', 'registration_fee')->count());

        $updatedSettings = $this->completeSettings();
        $updatedSettings['registration_fee'] = '975.50';
        $this->put(route('admin.settings.update'), $updatedSettings)->assertRedirect();
        $this->assertSame('975.50', $sabha->setting('registration_fee'));
        $this->assertSame($beforeCount, PradeshiyaSabhaSetting::query()->where('pradeshiya_sabha_id', $sabha->id)->count());
    }

    public function test_incomplete_configuration_blocks_registration_with_a_user_facing_error(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'BAL')->firstOrFail();
        $admin = $this->createSabhaAdmin($sabha);
        $payload = $this->registrationPayload($sabha);
        unset($payload['membership_type'], $payload['pradeshiya_sabha_id']);
        $payload['membership_type_id'] = MembershipType::query()->whereRaw('upper(name) = ?', ['CHILDREN'])->value('id');

        $this->actingAs($admin)
            ->from(route('admin.members.create'))
            ->post(route('admin.members.store'), $payload)
            ->assertRedirect(route('admin.members.create'))
            ->assertSessionHasErrors(['settings']);

        $settingsError = session('errors')->first('settings');
        $this->assertStringContainsString('registration_fee', $settingsError);
        $this->assertStringContainsString('currency', $settingsError);
        $this->assertDatabaseMissing('users', ['email' => 'blocked-registration@example.test']);
        $this->assertDatabaseMissing('members', ['email' => 'blocked-registration@example.test']);
    }

    public function test_incomplete_registration_configuration_does_not_block_other_sabha_operations(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'BAL')->firstOrFail();
        $admin = $this->createSabhaAdmin($sabha);
        $author = Author::create([
            'name' => 'Operational Settings Author',
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $publisher = Publisher::create([
            'name' => 'Operational Settings Publisher',
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $category = \App\Models\Category::create([
            'code' => '650',
            'name' => 'Operational Settings Category',
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $book = Book::create([
            'book_id' => 'SETTING-BOOK-'.$sabha->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => 'Book with incomplete library settings',
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '650.1',
            'status' => 'available',
        ]);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'accession_number' => 'SETTING-COPY-'.$sabha->id,
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
        $this->get(route('admin.books.index'))->assertOk()->assertSee($book->title);
        $this->get(route('admin.issue-books'))->assertOk()->assertSee('14');
        $this->get(route('admin.renewals.index'))->assertOk()->assertSee('No borrowing records found.');
        $this->get(route('admin.returns.index'))->assertOk();
        $this->assertSame(
            2,
            app(\App\Services\PaymentService::class)->integerSetting('maximum_renewals', 2)
        );

        $memberUser = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $membershipType = MembershipType::query()->assignable()->firstOrFail();
        $member = Member::create([
            'user_id' => $memberUser->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'membership_type_id' => $membershipType->id,
            'full_name' => $memberUser->name,
            'email' => $memberUser->email,
            'nic' => 'INCOMPLETE-SETTINGS-'.$memberUser->id,
            'phone' => '0771234567',
            'address' => 'Test address',
            'registration_fee' => '0',
            'registration_date' => today(),
            'membership_start_date' => today(),
            'expiry_date' => today()->addMonth(),
            'status' => 'ACTIVE',
        ]);

        $this->post(route('admin.borrowings.store'), [
            'member_id' => $member->id,
            'book_id' => $book->id,
        ])->assertRedirect(route('admin.borrowings.index'));

        $this->assertDatabaseHas('borrowings', [
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => $copy->id,
            'due_date' => today()->addDays(14)->toDateString(),
        ]);

        $borrowing = Borrowing::query()->where('member_id', $member->id)->firstOrFail();
        $this->post(route('admin.borrowings.renew', $borrowing))
            ->assertRedirect()
            ->assertSessionHas('success', 'Book renewed successfully.');
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'renewal_count' => 1,
            'due_date' => today()->addDays(28)->toDateString(),
        ]);

        $this->post(route('admin.borrowings.return', $borrowing))
            ->assertRedirect()
            ->assertSessionHas('success', 'Book returned successfully.');
        $this->assertDatabaseHas('borrowings', [
            'id' => $borrowing->id,
            'status' => 'RETURNED',
            'return_date' => today()->toDateString(),
        ]);
        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_missing_sabha_borrowing_limit_uses_existing_membership_type_limit_but_invalid_limit_fails(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'BAL')->firstOrFail();
        $admin = $this->createSabhaAdmin($sabha);
        $membershipType = MembershipType::query()->assignable()->firstOrFail();
        $configuredDefault = (int) $membershipType->getRawOriginal('max_books');

        $this->actingAs($admin);
        $this->assertSame($configuredDefault, $membershipType->max_books);

        PradeshiyaSabhaSetting::query()
            ->where('pradeshiya_sabha_id', $sabha->id)
            ->where('key', 'individual_borrowing_limit')
            ->update(['value' => 'invalid']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The Sabha-specific borrowing limit is invalid.');
        $membershipType->refresh()->max_books;
    }

    public function test_operational_setting_fallback_does_not_hide_invalid_tenant_values(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'BAL')->firstOrFail();
        $admin = $this->createSabhaAdmin($sabha);
        PradeshiyaSabhaSetting::query()
            ->where('pradeshiya_sabha_id', $sabha->id)
            ->where('key', 'issue_period_days')
            ->update(['value' => '0']);

        $this->actingAs($admin);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The Sabha-specific library setting 'issue_period_days' is invalid.");
        app(PradeshiyaSabhaSettingsService::class)->value('issue_period_days', '14');
    }

    public function test_completed_configuration_allows_registration_and_uses_sabha_values(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'AMB')->firstOrFail();
        $settings = $this->completeSettings();
        $settings['registration_fee'] = '43.25';
        $settings['membership_duration_months'] = '7';
        $this->saveSettings($sabha, $settings);
        $admin = $this->createSabhaAdmin($sabha);
        $payload = $this->registrationPayload($sabha);
        unset($payload['membership_type'], $payload['pradeshiya_sabha_id']);
        $payload['membership_type_id'] = MembershipType::query()->whereRaw('upper(name) = ?', ['CHILDREN'])->value('id');

        $this->actingAs($admin)
            ->post(route('admin.members.store'), $payload)
            ->assertRedirect();

        $member = Member::query()->where('email', 'blocked-registration@example.test')->firstOrFail();
        $this->assertSame($sabha->id, (int) $member->pradeshiya_sabha_id);
        $this->assertSame('43.25', $member->registration_fee);
        $this->assertSame(today()->addMonths(7)->toDateString(), $member->expiry_date->toDateString());
    }

    public function test_sabha_admin_and_member_cannot_open_or_update_settings(): void
    {
        $sabhaA = PradeshiyaSabha::query()->where('code', 'BAD')->firstOrFail();
        $sabhaB = PradeshiyaSabha::query()->where('code', 'BEN')->firstOrFail();
        $sabhaAdmin = User::factory()->create([
            'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabhaA->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'user_id' => $sabhaAdmin->id,
            'is_active' => true,
        ]);

        $this->actingAs($sabhaAdmin)
            ->get(route('admin.sabhas.show', $sabhaA))
            ->assertRedirect(route('admin.dashboard'));
        $this->put(route('admin.settings.update'), [
            ...$this->completeSettings(),
            'sabha_id' => $sabhaB->id,
        ])->assertSessionHasErrors('settings');
        $this->assertNull($sabhaB->setting('registration_fee'));

        $member = User::factory()->create(['role' => User::ROLE_MEMBER, 'is_active' => true]);
        $this->actingAs($member)->get(route('admin.sabhas.show', $sabhaA))->assertRedirect(route('member.dashboard'));
    }

    public function test_invalid_fees_integers_currency_timezone_and_unknown_keys_are_rejected(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'ELP')->firstOrFail();
        $admin = $this->createSabhaAdmin($sabha);
        $this->actingAs($admin);

        $invalid = $this->completeSettings();
        $invalid['registration_fee'] = '-1';
        $this->from(route('admin.sabhas.show', $sabha))
            ->put(route('admin.settings.update'), $invalid)
            ->assertSessionHasErrors('registration_fee');

        $invalid = $this->completeSettings();
        $invalid['membership_duration_months'] = '0';
        $this->from(route('admin.sabhas.show', $sabha))
            ->put(route('admin.settings.update'), $invalid)
            ->assertSessionHasErrors('membership_duration_months');

        $invalid = $this->completeSettings();
        $invalid['maximum_renewals'] = '-1';
        $this->from(route('admin.sabhas.show', $sabha))
            ->put(route('admin.settings.update'), $invalid)
            ->assertSessionHasErrors('maximum_renewals');

        $invalid = $this->completeSettings();
        $invalid['currency'] = 'ZZZ';
        $this->from(route('admin.sabhas.show', $sabha))
            ->put(route('admin.settings.update'), $invalid)
            ->assertSessionHasErrors('currency');

        $invalid = $this->completeSettings();
        $invalid['timezone'] = 'Not/A_Timezone';
        $this->from(route('admin.sabhas.show', $sabha))
            ->put(route('admin.settings.update'), $invalid)
            ->assertSessionHasErrors('timezone');

        $invalid = $this->completeSettings();
        $invalid['unapproved_setting'] = 'unexpected';
        $this->from(route('admin.sabhas.show', $sabha))
            ->put(route('admin.settings.update'), $invalid)
            ->assertSessionHasErrors('settings');

        $this->assertNull($sabha->setting('registration_fee'));
    }

    public function test_settings_service_reports_invalid_stored_values_as_incomplete(): void
    {
        $sabha = PradeshiyaSabha::query()->where('code', 'HAB')->firstOrFail();
        $settings = $this->completeSettings();
        $settings['timezone'] = 'Not/A_Timezone';
        $this->saveSettings($sabha, $settings);

        $status = app(PradeshiyaSabhaSettingsService::class)->configurationStatus($sabha->fresh());
        $this->assertFalse($status['complete']);
        $this->assertSame(['timezone'], $status['invalid']);
        $admin = $this->createSabhaAdmin($sabha);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Invalid:')
            ->assertSee('Timezone');
    }

    private function completeSettings(): array
    {
        return [
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

    private function createSabhaAdmin(PradeshiyaSabha $sabha): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user;
    }

    private function saveSettings(PradeshiyaSabha $sabha, array $settings): void
    {
        foreach ($settings as $key => $value) {
            PradeshiyaSabhaSetting::query()->updateOrCreate(
                ['pradeshiya_sabha_id' => $sabha->id, 'key' => $key],
                ['value' => (string) $value]
            );
        }
    }

    private function registrationPayload(PradeshiyaSabha $sabha): array
    {
        return [
            'full_name' => 'Settings Test Member',
            'email' => 'blocked-registration@example.test',
            'password' => 'test-password-unique',
            'password_confirmation' => 'test-password-unique',
            'nic' => 'SETTINGS-TEST-NIC',
            'phone' => '0771234567',
            'address' => 'Test address',
            'password' => 'test-password-unique',
            'password_confirmation' => 'test-password-unique',
            'pradeshiya_sabha_id' => $sabha->id,
        ];
    }
}
