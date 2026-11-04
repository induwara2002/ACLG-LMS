<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pages_render_the_hidden_shared_library_loader(): void
    {
        foreach (['/', '/login'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('class="page-loader" data-page-loader aria-hidden="true"', false)
                ->assertSee('ප්‍රාදේශීය සභා පුස්තකාල කළමනාකරණ පද්ධතිය')
                ->assertSee('පිටුව පූරණය වෙමින් පවතී...')
                ->assertSee('images/government-logo.png', false)
                ->assertSee('js/app.js', false)
                ->assertSee('css/app.css', false);
        }
    }

    public function test_library_splash_is_rendered_on_the_home_page_but_not_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-library-splash', false)
            ->assertSee('data-login-url=', false)
            ->assertSee('පුස්තකාල කළමනාකරණ පද්ධතිය')
            ->assertSee('PRADESHIYA SABHA LIBRARY MANAGEMENT SYSTEM')
            ->assertSee('data-splash-progress', false)
            ->assertSee('data-splash-percent', false);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('data-library-splash', false);
    }

    public function test_authenticated_home_page_uses_the_splash_without_changing_authentication(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('data-library-splash', false)
            ->assertSee('data-login-url=', false);
    }

    public function test_each_authenticated_workspace_layout_includes_one_shared_loader(): void
    {
        foreach (['admin', 'member', 'librarian'] as $layout) {
            $contents = file_get_contents(resource_path('views/layouts/'.$layout.'.blade.php'));

            $this->assertNotFalse($contents);
            $this->assertSame(1, substr_count($contents, '<x-page-loader />'), $layout);
        }
    }
}
