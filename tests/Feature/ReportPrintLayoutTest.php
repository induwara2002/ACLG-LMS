<?php

namespace Tests\Feature;

use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportPrintLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_report_renders_the_shared_print_header_and_footer(): void
    {
        $mainAdmin = User::factory()->create([
            'role' => User::ROLE_MAIN_ADMIN,
            'is_active' => true,
        ]);

        foreach ([
            'books',
            'members',
            'borrowings',
            'overdue',
            'returns',
            'payments',
            'fines',
            'lost-books',
            'damaged-books',
            'compensation',
        ] as $report) {
            $this->actingAs($mainAdmin)
                ->get(route('admin.reports.show', $report))
                ->assertOk()
                ->assertSee('report-print-header', false)
                ->assertSee(asset('images/sri-lanka-state-emblem.png'), false)
                ->assertSee('report-print-footer', false)
                ->assertSee(__('reports.all_pradeshiya_sabhas'))
                ->assertSee(__('reports.generated_at'));
        }
    }

    public function test_approved_state_emblem_is_present_and_readable(): void
    {
        $logoPath = public_path('images/sri-lanka-state-emblem.png');

        $this->assertFileExists($logoPath);
        $this->assertTrue(is_readable($logoPath));
        $this->assertGreaterThan(0, filesize($logoPath));
        $this->assertSame("\x89PNG\r\n\x1a\n", file_get_contents($logoPath, false, null, 0, 8));
    }

    public function test_report_header_uses_the_selected_tenants_sinhala_name_and_private_logo(): void
    {
        $sabha = PradeshiyaSabha::create([
            'code' => 'RPTA',
            'name' => 'Report Tenant A',
        ]);
        $otherSabha = PradeshiyaSabha::create([
            'code' => 'RPTB',
            'name' => 'Report Tenant B',
        ]);
        $sabha->settings()->createMany([
            ['key' => 'name_si', 'value' => 'වාර්තා සභාව'],
            ['key' => 'library_name', 'value' => 'Tenant A Library'],
        ]);
        Storage::disk('local')->put('pradeshiya-sabhas/'.$sabha->id.'/logo.png', 'approved-logo');
        $sabha->update(['logo_path' => 'pradeshiya-sabhas/'.$sabha->id.'/logo.png']);
        $sabhaAdmin = $this->createSabhaAdmin($sabha);

        $response = $this->actingAs($sabhaAdmin)
            ->get(route('admin.reports.show', 'books'))
            ->assertOk()
            ->assertSee('වාර්තා සභාව')
            ->assertSee('Report Tenant A')
            ->assertSee('Tenant A Library')
            ->assertDontSee('Report Tenant B')
            ->assertDontSee(__('reports.official_logo_not_configured'));
        preg_match('/<header class="report-print-header">(.*?)<\/header>/s', $response->getContent(), $header);
        $this->assertStringContainsString(route('sabhas.logo', $sabha), $header[1] ?? '');
        $this->assertStringNotContainsString(asset('images/sri-lanka-state-emblem.png'), $header[1] ?? '');

        $mainAdmin = User::factory()->create([
            'role' => User::ROLE_MAIN_ADMIN,
            'is_active' => true,
        ]);
        $response = $this->actingAs($mainAdmin)
            ->get(route('admin.reports.show', ['report' => 'books', 'sabha_id' => $sabha->id]))
            ->assertOk();
        preg_match('/<header class="report-print-header">(.*?)<\/header>/s', $response->getContent(), $header);
        $this->assertStringContainsString('වාර්තා සභාව', $header[1] ?? '');
        $this->assertStringContainsString('Report Tenant A', $header[1] ?? '');
        $this->assertStringNotContainsString('Report Tenant B', $header[1] ?? '');

        $this->assertNotSame($sabha->id, $otherSabha->id);
    }

    public function test_report_header_uses_official_emblem_when_sabha_logo_is_unconfigured(): void
    {
        $sabha = PradeshiyaSabha::create([
            'code' => 'RPTC',
            'name' => 'Report Tenant C',
        ]);
        $sabhaAdmin = $this->createSabhaAdmin($sabha);

        $this->actingAs($sabhaAdmin)
            ->get(route('admin.reports.show', [
                'report' => 'members',
                'date_from' => '2026-01-02',
                'date_to' => '2026-02-03',
            ]))
            ->assertOk()
            ->assertSee(__('reports.sinhala_name_not_configured'))
            ->assertSee('Report Tenant C')
            ->assertSee(asset('images/sri-lanka-state-emblem.png'), false)
            ->assertSee('2026-01-02')
            ->assertSee('2026-02-03');
    }

    public function test_sabha_admin_can_configure_the_sinhala_sabha_name_in_library_settings(): void
    {
        $sabha = PradeshiyaSabha::create([
            'code' => 'RPTD',
            'name' => 'Report Tenant D',
        ]);
        $sabhaAdmin = $this->createSabhaAdmin($sabha);

        $this->actingAs($sabhaAdmin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('name_si');

        $this->put(route('admin.settings.update'), [
            'name_si' => 'සිංහල සභා නාමය',
            'registration_fee' => '100.00',
            'monthly_membership_fee' => '20.00',
            'late_renewal_fee' => '5.00',
            'late_renewal_penalty' => '1.00',
            'overdue_fine_amount' => '2.00',
            'membership_duration_months' => 12,
            'individual_borrowing_limit' => 2,
            'family_borrowing_limit' => 3,
            'issue_period_days' => 14,
            'maximum_renewals' => 2,
            'currency' => 'LKR',
            'timezone' => 'Asia/Colombo',
        ])->assertRedirect();

        $this->assertSame('සිංහල සභා නාමය', $sabha->fresh()->setting('name_si'));
    }

    private function createSabhaAdmin(PradeshiyaSabha $sabha): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SABHA_ADMIN,
            'pradeshiya_sabha_id' => $sabha->id,
            'is_active' => true,
        ]);

        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user;
    }
}
