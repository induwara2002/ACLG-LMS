<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_isbn_is_normalized_and_google_books_metadata_is_returned(): void
    {
        $admin = $this->sabhaAdmin();
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'volumeInfo' => [
                        'title' => 'A Book Title',
                        'authors' => ['Existing Author'],
                        'publisher' => 'Example Press',
                        'publishedDate' => '2001-02-03',
                        'edition' => 'Second edition',
                        'description' => 'Book description.',
                        'language' => 'en',
                    ],
                ]],
            ]),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', ['isbn' => '978-0-306-40615-7']));

        $response->assertOk()->assertExactJson([
            'found' => true,
            'metadata' => [
                'isbn' => '9780306406157',
                'title' => 'A Book Title',
                'authors' => ['Existing Author'],
                'publisher' => 'Example Press',
                'publication_year' => 2001,
                'edition' => 'Second edition',
                'description' => 'Book description.',
                'language' => 'en',
            ],
        ]);
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://www.googleapis.com/books/v1/volumes?q=isbn%3A9780306406157&maxResults=1&printType=books');
    }

    public function test_invalid_isbn_is_rejected_without_calling_google_books(): void
    {
        $admin = $this->sabhaAdmin();
        Http::fake();

        $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', ['isbn' => '978-0-306-40615-8']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');

        Http::assertNothingSent();
    }

    public function test_isbn_ten_is_also_accepted_and_normalized(): void
    {
        $admin = $this->sabhaAdmin();
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [['volumeInfo' => ['title' => 'ISBN-10 Book']]],
            ]),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', ['isbn' => '0-306-40615-2']))
            ->assertOk()
            ->assertJsonPath('metadata.isbn', '0306406152')
            ->assertJsonPath('metadata.title', 'ISBN-10 Book');
    }

    public function test_no_results_are_reported_and_cached(): void
    {
        $admin = $this->sabhaAdmin();
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response(['totalItems' => 0]),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', ['isbn' => '9780306406157']))
            ->assertOk()
            ->assertJson([
                'found' => false,
                'message' => 'No matching book was found. You can enter the details manually.',
            ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', ['isbn' => '978 0 306 40615 7']))
            ->assertOk()
            ->assertJsonPath('found', false);

        Http::assertSentCount(1);
    }

    public function test_google_books_failure_returns_a_friendly_gateway_error(): void
    {
        $admin = $this->sabhaAdmin();
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([], 503),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', ['isbn' => '9780306406157']))
            ->assertStatus(502)
            ->assertJsonPath(
                'message',
                'Metadata lookup is temporarily unavailable. You can enter the book details manually.'
            );
    }

    public function test_repeated_lookup_is_cached_and_editing_the_same_isbn_is_not_reported_as_a_duplicate(): void
    {
        $admin = $this->sabhaAdmin();
        $book = $this->bookFor($admin);
        $cacheKey = 'google-books.isbn.'.hash('sha256', '9780306406157');
        Cache::forget($cacheKey);

        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [['volumeInfo' => ['title' => 'Original Title']]],
            ]),
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', [
                'isbn' => '9780306406157',
                'current_book_id' => $book->id,
            ]))
            ->assertOk()
            ->assertJsonPath('metadata.title', 'Original Title');

        $this->actingAs($admin)
            ->getJson(route('admin.books.isbn-lookup', [
                'isbn' => '9780306406157',
                'current_book_id' => $book->id,
            ]))
            ->assertOk()
            ->assertJsonPath('metadata.title', 'Original Title');

        Http::assertSentCount(1);
    }

    public function test_create_and_edit_forms_share_the_lookup_ui_and_existing_cover_upload(): void
    {
        $admin = $this->sabhaAdmin();
        $book = $this->bookFor($admin);

        $this->actingAs($admin)
            ->get(route('admin.books.create'))
            ->assertOk()
            ->assertSee('id="isbn-lookup"', false)
            ->assertSee('id="isbn-lookup-status"', false)
            ->assertSee('js/isbn-lookup.js')
            ->assertSee('id="cover_image"', false);

        $this->actingAs($admin)
            ->get(route('admin.books.edit', $book))
            ->assertOk()
            ->assertSee('id="isbn-lookup"', false)
            ->assertSee('data-current-book-id="'.$book->id.'"', false)
            ->assertSee('id="cover_image"', false);
    }

    private function sabhaAdmin(): User
    {
        $sabha = PradeshiyaSabha::create([
            'code' => 'GB'.substr(uniqid(), 0, 8),
            'name' => 'Google Books Sabha '.uniqid(),
        ]);
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

    private function bookFor(User $admin): Book
    {
        $author = Author::create([
            'name' => 'Existing Author',
            'pradeshiya_sabha_id' => $admin->pradeshiya_sabha_id,
        ]);
        $publisher = Publisher::create([
            'name' => 'Example Press',
            'pradeshiya_sabha_id' => $admin->pradeshiya_sabha_id,
        ]);
        $category = Category::query()
            ->where('pradeshiya_sabha_id', $admin->pradeshiya_sabha_id)
            ->where('code', '000')
            ->firstOrFail();

        return Book::create([
            'book_id' => 'GOOGLE-'.uniqid(),
            'pradeshiya_sabha_id' => $admin->pradeshiya_sabha_id,
            'title' => 'Stored Title',
            'isbn' => '9780306406157',
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '000',
            'status' => 'available',
        ]);
    }
}
