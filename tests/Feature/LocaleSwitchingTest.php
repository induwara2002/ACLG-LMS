<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_between_english_and_sinhala(): void
    {
        $this->from('/')
            ->post('/language', ['locale' => 'si'])
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'si')
            ->assertCookie('ui_locale', 'si');

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="si">', false)
            ->assertSee('මුල් පිටුව');

        $this->from('/')
            ->post('/language', ['locale' => 'en'])
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'en');

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Home');
    }

    public function test_language_switcher_is_visible_on_home_but_not_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('name="locale"', false)
            ->assertSee('class="language-switcher"', false);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('name="locale"', false)
            ->assertDontSee('class="language-switcher"', false);
    }

    public function test_selected_locale_is_preserved_through_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'locale-admin@example.com',
            'role' => User::ROLE_MAIN_ADMIN,
            'is_active' => true,
        ]);

        $this->from('/login')->post('/language', ['locale' => 'si']);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('<html lang="si">', false)
            ->assertSee('උපකරණ පුවරුව');
    }

    public function test_authentication_errors_use_the_selected_locale(): void
    {
        $this->from('/login')->post('/language', ['locale' => 'si']);

        $this->from('/login')->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'incorrect-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'විද්‍යුත් තැපැල් ලිපිනය හෝ මුරපදය නිවැරදි නොවේ.',
            ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('ආරක්ෂිත ගිණුම් පිවිසුම')
            ->assertSee('විද්‍යුත් තැපැල් ලිපිනය හෝ මුරපදය නිවැරදි නොවේ.');
    }

    public function test_authenticated_role_dashboards_share_the_language_switcher_and_keep_the_selected_locale(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'LOCALE', 'name' => 'Locale Sabha']);
        $sabhaAdmin = User::factory()->create([
            'role' => User::ROLE_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $sabhaAdmin->id,
            'is_active' => true,
        ]);

        $membershipType = MembershipType::create(['name' => 'Locale Member', 'max_books' => 2]);
        $memberUser = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        Member::create([
            'user_id' => $memberUser->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'membership_type_id' => $membershipType->id,
            'full_name' => $memberUser->name,
            'email' => $memberUser->email,
            'registration_date' => today(),
            'membership_start_date' => today(),
            'expiry_date' => today()->addMonth(),
            'status' => 'ACTIVE',
        ]);

        $roles = [
            [User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]), '/admin/dashboard', '/admin/books', 'ප්‍රධාන පරිපාලක උපකරණ පුවරුව', 'ප්‍රාදේශීය සභා කළමනාකරණය'],
            [$sabhaAdmin, '/admin/dashboard', '/admin/books', 'පුස්තකාලයේ සාමාජිකයින්', 'පුස්තකාල සැකසුම්'],
            [User::factory()->create([
                'role' => User::ROLE_LIBRARIAN,
                'is_active' => true,
                'pradeshiya_sabha_id' => $sabha->id,
            ]), '/librarian/dashboard', '/librarian/books/search', 'පුස්තකාලයාධිපති උපකරණ පුවරුව', 'සාමාජිකයින්'],
            [$memberUser, '/member/dashboard', '/member/books/search', 'සාමාජික උපකරණ පුවරුව', 'පොත් පිරික්සන්න'],
        ];

        foreach ($roles as [$user, $dashboard, $nextPage, $translatedDashboardText, $translatedNavigationText]) {
            $this->actingAs($user)
                ->from($dashboard)
                ->post('/language', ['locale' => 'si'])
                ->assertRedirect($dashboard)
                ->assertSessionHas('locale', 'si');

            $this->get($dashboard)
                ->assertOk()
                ->assertSee('<html lang="si">', false)
                ->assertSee('name="locale"', false)
                ->assertSee('value="si" selected', false)
                ->assertSee($translatedDashboardText)
                ->assertSee('🌐', false)
                ->assertSee('භාෂාව')
                ->assertSee($translatedNavigationText);

            $this->get($nextPage)
                ->assertOk()
                ->assertSee('<html lang="si">', false);

            $this->from($dashboard)
                ->post('/language', ['locale' => 'en'])
                ->assertRedirect($dashboard)
                ->assertSessionHas('locale', 'en');

            $this->get($nextPage)
                ->assertOk()
                ->assertSee('<html lang="en">', false);
        }
    }

    public function test_selected_locale_is_restored_from_cookie_after_logout(): void
    {
        $admin = User::factory()->create([
            'email' => 'locale-logout-admin@example.com',
            'role' => User::ROLE_MAIN_ADMIN,
            'is_active' => true,
        ]);

        $this->from('/')
            ->post('/language', ['locale' => 'si'])
            ->assertCookie('ui_locale', 'si');

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/admin/dashboard');

        $this->post('/logout')->assertRedirect('/login');

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="si">', false)
            ->assertSee('මුල් පිටුව');
    }

    public function test_locale_switcher_rejects_unsupported_locales(): void
    {
        $this->from('/')->post('/language', ['locale' => 'fr'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale');
    }
}
