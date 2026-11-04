<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\Publisher;
use App\Models\User;
use Database\Seeders\DDCSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_search_matches_shared_book_metadata_without_exposing_accession_numbers(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'PUB'.substr(uniqid(), 0, 9), 'name' => 'Public Catalog Sabha']);
        $author = Author::create(['name' => 'Ada Lovelace', 'pradeshiya_sabha_id' => $sabha->id]);
        $publisher = Publisher::create(['name' => 'Civic Press', 'pradeshiya_sabha_id' => $sabha->id]);
        $category = Category::create(['code' => '641', 'name' => 'Cooking', 'pradeshiya_sabha_id' => $sabha->id]);
        $book = Book::create([
            'book_id' => 'CAT-001',
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => 'A Sample Community Cookbook',
            'isbn' => '9780000000001',
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '641.5',
            'publication_year' => 2025,
            'language' => 'English',
        ]);
        BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'accession_number' => 'GAL-ACC-009',
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => now()->toDateString(),
        ]);

        foreach (['sAmPlE', 'lovelace', '9780000000001', 'civic press', 'cOoKiNg'] as $term) {
            $this->get(route('public.books.search', ['search' => $term]))
                ->assertOk()
                ->assertSee('A Sample Community Cookbook');
        }

        $this->get(route('public.books.search', ['search' => 'gal-acc-009']))
            ->assertOk()
            ->assertDontSee('A Sample Community Cookbook');

        $this->get(route('public.books.show', $book))
            ->assertOk()
            ->assertSee('Civic Press')
            ->assertDontSee('GAL-ACC-009');
    }

    public function test_public_catalog_search_requires_a_bounded_search_term(): void
    {
        $this->get(route('public.books.search'))
            ->assertOk()
            ->assertSee('Books in the collection');

        $this->from('/')->get(route('public.books.search', ['search' => str_repeat('x', 256)]))
            ->assertRedirect('/')
            ->assertSessionHasErrors('search');
    }

    public function test_admin_can_create_author(): void
    {
        $admin = $this->sabhaAdmin();

        $this->actingAs($admin)
            ->post('/admin/authors', [
                'name' => 'Jane Rowling',
                'biography' => 'A well-known author.',
                'contact' => 'jane@example.com',
            ])
            ->assertRedirect('/admin/authors');

        $this->assertDatabaseHas('authors', ['name' => 'Jane Rowling']);
    }

    public function test_member_cannot_create_author(): void
    {
        $member = User::factory()->create(['role' => 'MEMBER', 'is_active' => true]);

        $this->actingAs($member)
            ->post('/admin/authors', [
                'name' => 'Blocked Author',
            ])
            ->assertRedirect('/member/dashboard');
    }

    public function test_ddc_seed_synchronizes_only_requested_ranges_for_all_existing_sabhas_idempotently(): void
    {
        $firstSabha = PradeshiyaSabha::withoutEvents(fn () => PradeshiyaSabha::create([
            'code' => 'DDC1'.substr(uniqid(), 0, 5),
            'name' => 'First DDC Sabha',
        ]));
        $secondSabha = PradeshiyaSabha::withoutEvents(fn () => PradeshiyaSabha::create([
            'code' => 'DDC2'.substr(uniqid(), 0, 5),
            'name' => 'Second DDC Sabha',
        ]));

        $firstSabha->categories()->create([
            'code' => '000',
            'name' => 'Legacy category name',
            'description' => 'Legacy range description',
        ]);

        $this->seed(DDCSeeder::class);

        $expectedCodes = ['000', '100', '300', '500', '700', '900'];
        foreach ([$firstSabha, $secondSabha] as $sabha) {
            $this->assertSame(
                $expectedCodes,
                $sabha->categories()->orderBy('code')->pluck('code')->all()
            );

            foreach (Category::BOOK_REGISTRATION_CATEGORIES as $code => $range) {
                $this->assertDatabaseHas('categories', [
                    'pradeshiya_sabha_id' => $sabha->id,
                    'code' => $code,
                    'name' => $range['name'],
                    'description' => 'DDC range '.$range['label'],
                ]);
            }
        }

        $this->assertNotSame(
            $firstSabha->categories()->where('code', '000')->value('id'),
            $secondSabha->categories()->where('code', '000')->value('id')
        );
        $this->assertDatabaseMissing('categories', ['pradeshiya_sabha_id' => $firstSabha->id, 'code' => '200']);
        $this->assertDatabaseMissing('categories', ['pradeshiya_sabha_id' => $firstSabha->id, 'code' => '400']);
        $this->assertDatabaseMissing('categories', ['pradeshiya_sabha_id' => $firstSabha->id, 'code' => '600']);
        $this->assertDatabaseMissing('categories', ['pradeshiya_sabha_id' => $firstSabha->id, 'code' => '800']);

        $this->seed(DDCSeeder::class);
        foreach ([$firstSabha, $secondSabha] as $sabha) {
            $this->assertSame(6, $sabha->categories()->count());
        }
    }

    public function test_new_sabha_receives_tenant_owned_ddc_ranges_automatically(): void
    {
        $sabha = PradeshiyaSabha::create([
            'code' => 'NEW'.substr(uniqid(), 0, 9),
            'name' => 'New DDC Sabha',
        ]);

        $this->assertSame(
            ['000', '100', '300', '500', '700', '900'],
            $sabha->categories()->orderBy('code')->pluck('code')->all()
        );
        $this->assertSame(6, Category::query()->where('pradeshiya_sabha_id', $sabha->id)->count());
    }

    public function test_ddc_range_boundaries_are_half_open_and_book_form_displays_ranges(): void
    {
        $admin = $this->sabhaAdmin();
        $range000 = Category::query()->where('pradeshiya_sabha_id', $admin->pradeshiya_sabha_id)->where('code', '000')->firstOrFail();
        $range100 = Category::query()->where('pradeshiya_sabha_id', $admin->pradeshiya_sabha_id)->where('code', '100')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/books/create')
            ->assertOk()
            ->assertSee('000-100')
            ->assertSee('900-1000');

        $this->actingAs($admin)
            ->from('/admin/categories/create')
            ->post('/admin/categories', [
                'parent_id' => $range000->id,
                'code' => '100',
                'name' => 'Boundary category',
            ])
            ->assertRedirect('/admin/categories/create')
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post('/admin/categories', [
                'parent_id' => $range100->id,
                'code' => '100.1',
                'name' => 'Category within the 100 range',
            ])
            ->assertRedirect('/admin/categories')
            ->assertSessionHasNoErrors();
    }

    public function test_admin_can_create_book_with_copies(): void
    {
        $admin = $this->sabhaAdmin();
        $author = Author::create(['name' => 'Author A', 'pradeshiya_sabha_id' => $admin->pradeshiya_sabha_id]);
        $publisher = Publisher::create(['name' => 'Publisher A', 'pradeshiya_sabha_id' => $admin->pradeshiya_sabha_id]);
        $category = Category::query()
            ->where('pradeshiya_sabha_id', $admin->pradeshiya_sabha_id)
            ->where('code', '900')
            ->firstOrFail();

        $response = $this->actingAs($admin)->post('/admin/books', [
            'book_id' => 'BK-1001',
            'isbn' => '9780306406157',
            'title' => 'Python Programming',
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '900.2',
            'edition' => '2nd',
            'publication_year' => 2024,
            'language' => 'English',
            'shelf_number' => 'QA-12',
            'total_copies' => 2,
            'description' => 'A sample book',
            'status' => 'AVAILABLE',
        ]);

        $response->assertRedirect('/admin/books');
        $this->assertDatabaseHas('books', ['book_id' => 'BK-1001', 'isbn' => '9780306406157']);
        $this->assertDatabaseCount('book_copies', 2)->assertTrue(true);
    }

    private function sabhaAdmin(): User
    {
        $sabha = PradeshiyaSabha::create(['code' => 'CAT'.substr(uniqid(), 0, 9), 'name' => 'Catalog Sabha']);
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
}
