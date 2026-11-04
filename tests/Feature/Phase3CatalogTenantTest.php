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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3CatalogTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_form_creates_required_acquisition_and_physical_copies_without_description(): void
    {
        [$sabha, $admin] = $this->tenant('FORM');
        $author = $this->author($sabha, 'Kumara K.J');
        $publisher = $this->publisher($sabha, 'Harbor Press');
        $category = $this->category($sabha, '650', 'Library Science');

        $this->actingAs($admin)
            ->get(route('admin.books.index'))
            ->assertOk()
            ->assertSee('<a href="'.route('admin.books.create').'" class="btn btn-primary">Add Book</a>', false);
        $this->get(route('admin.books.create'))
            ->assertOk()
            ->assertSee('Book title *')
            ->assertSee('Acquisition number *')
            ->assertSee('name="author_name"', false)
            ->assertSee('placeholder="Enter author name"', false)
            ->assertSee('name="publisher_name"', false)
            ->assertSee('placeholder="Enter publisher name"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="language"', false)
            ->assertSee('name="publication_year"', false)
            ->assertSee('name="total_copies"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('type="submit"', false)
            ->assertSee('action="'.route('admin.books.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('Description');
        $this->get(route('admin.dashboard'))
            ->assertSee('Categories')
            ->assertDontSee('Authors')
            ->assertDontSee('Publishers');

        $payload = $this->registrationPayload('ACQ-FORM-1', $author, $publisher, $category) + ['total_copies' => 2];
        $payload['publication_year'] = 1890;
        $payload['description'] = 'Must not be stored from the removed form field.';
        $otherSabha = PradeshiyaSabha::create(['code' => 'FORM-B', 'name' => 'Forged Form Sabha']);
        $payload['pradeshiya_sabha_id'] = $otherSabha->id;

        Log::spy();
        $this->post(route('admin.books.store'), $payload)
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book added successfully.');

        $book = Book::query()->where('book_id', 'ACQ-FORM-1')->firstOrFail();
        $this->assertSame($sabha->id, (int) $book->pradeshiya_sabha_id);
        $this->assertSame(1890, (int) $book->publication_year);
        $this->assertNull($book->description);
        $this->assertSame($author->id, $book->author_id);
        $this->assertSame($publisher->id, $book->publisher_id);
        $this->assertSame(2, $book->copies()->count());
        $this->assertDatabaseHas('book_copies', ['book_id' => $book->id, 'accession_number' => 'FORM-ACQ-FORM-1-001']);
        $this->assertDatabaseHas('book_copies', ['book_id' => $book->id, 'accession_number' => 'FORM-ACQ-FORM-1-002']);
        $this->get(route('admin.books.index'))
            ->assertSee('Book added successfully.')
            ->assertSee('alert-success', false)
            ->assertSee('ACQ-FORM-1')
            ->assertSee('Catalog Test Book');
        Log::shouldHaveReceived('debug')
            ->with('book_creation_controller_reached', \Mockery::on(
                fn (array $context): bool => $context['user_id'] === $admin->id
                    && $context['tenant_id'] === $sabha->id
                    && $context['route'] === 'admin.books.store'
            ))
            ->once();
        Log::shouldHaveReceived('info')
            ->with('book_creation_persisted', \Mockery::on(
                fn (array $context): bool => $context['tenant_id'] === $sabha->id
                    && $context['persisted_copy_count'] === 2
            ))
            ->once();

        $this->from(route('admin.books.create'))
            ->post(route('admin.books.store'), array_diff_key($payload, ['book_id' => true, 'total_copies' => true]))
            ->assertSessionHasErrors(['book_id', 'total_copies']);
    }

    public function test_book_registration_offers_exact_ddc_ranges_and_saves_the_selected_category(): void
    {
        [$sabha, $admin] = $this->tenant('DDC-FORM');
        $author = $this->author($sabha, 'DDC Form Author');
        $publisher = $this->publisher($sabha, 'DDC Form Publisher');
        $unlistedCategory = $this->category($sabha, '999', 'Unlisted Category');

        $response = $this->actingAs($admin)->get(route('admin.books.create'))->assertOk();
        $html = $response->getContent();
        preg_match('/<select id="category_id"[^>]*>(.*?)<\/select>/s', $html, $categorySelect);
        $categoryHtml = $categorySelect[1] ?? '';

        $this->assertSame(8, preg_match_all('/<option\b/', $categoryHtml));
        $this->assertStringContainsString('<option value="">Select category</option>', $categoryHtml);
        $this->assertStringContainsString('name="author_id"', $html);
        $this->assertStringContainsString('name="publisher_id"', $html);
        $this->assertStringContainsString('value="'.$unlistedCategory->id.'"', $categoryHtml);

        $categoryOptions = [];
        foreach (Category::BOOK_REGISTRATION_CATEGORIES as $code => $details) {
            $category = Category::withoutGlobalScope(\App\Models\Scopes\PradeshiyaSabhaScope::class)
                ->where('code', $code)
                ->where('pradeshiya_sabha_id', $sabha->id)
                ->firstOrFail();
            $categoryOptions[] = $category->id;
            $this->assertStringContainsString(
                '<option value="'.$category->id.'" >'.$code.' - '.$details['label'].' · '.$details['name'].'</option>',
                $categoryHtml
            );
        }

        $this->assertSame(6, Category::withoutGlobalScope(\App\Models\Scopes\PradeshiyaSabhaScope::class)
            ->where('pradeshiya_sabha_id', $sabha->id)
            ->whereIn('code', array_keys(Category::BOOK_REGISTRATION_CATEGORIES))
            ->count());

        $this->get(route('admin.books.create'))->assertOk();
        $this->assertSame(6, Category::withoutGlobalScope(\App\Models\Scopes\PradeshiyaSabhaScope::class)
            ->where('pradeshiya_sabha_id', $sabha->id)
            ->whereIn('code', array_keys(Category::BOOK_REGISTRATION_CATEGORIES))
            ->count());

        $selectedCategoryId = $categoryOptions[5];
        $payload = $this->bookPayload(
            'ACQ-DDC-FORM',
            $author,
            $publisher,
            Category::withoutGlobalScope(\App\Models\Scopes\PradeshiyaSabhaScope::class)->findOrFail($selectedCategoryId)
        ) + ['total_copies' => 1];
        $payload['ddc_number'] = '900.1';

        $this->post(route('admin.books.store'), $payload)
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book added successfully.');

        $book = Book::query()->where('book_id', 'ACQ-DDC-FORM')->firstOrFail();
        $this->assertSame($selectedCategoryId, (int) $book->category_id);
        $this->assertSame($selectedCategoryId, (int) $book->category->id);

        $payload['book_id'] = 'ACQ-DDC-MISSING';
        unset($payload['category_id']);
        $this->from(route('admin.books.create'))
            ->post(route('admin.books.store'), $payload)
            ->assertSessionHasErrors('category_id');
    }

    public function test_book_cover_upload_preview_display_replacement_and_removal_are_safe(): void
    {
        Storage::fake('public');
        [$sabha, $admin] = $this->tenant('COVER');
        $author = $this->author($sabha, 'Cover Author');
        $publisher = $this->publisher($sabha, 'Cover Publisher');
        $category = $this->category($sabha, '650', 'Cover Category');

        $this->actingAs($admin)
            ->get(route('admin.books.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="cover_image"', false)
            ->assertSee('accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"', false)
            ->assertSee('images/book-placeholder.svg');

        $payload = $this->registrationPayload('ACQ-COVER', $author, $publisher, $category) + ['total_copies' => 1];
        $payload['cover_image'] = UploadedFile::fake()->image('../../unsafe-cover.png');
        $this->post(route('admin.books.store'), $payload)
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book added successfully.');

        $book = Book::query()->where('book_id', 'ACQ-COVER')->firstOrFail();
        $originalPath = $book->cover_image;
        $this->assertStringStartsWith('book-covers/', $originalPath);
        $this->assertSame(basename($originalPath), substr($originalPath, strlen('book-covers/')));
        $this->assertStringNotContainsString('..', $originalPath);
        $this->assertFalse(Book::isSafeCoverImagePath('../../outside.png'));
        Storage::disk('public')->assertExists($originalPath);

        $coverUrl = Storage::disk('public')->url($originalPath);
        $this->get(route('admin.books.index'))->assertOk()->assertSee($coverUrl, false);
        $this->get(route('admin.books.show', $book))->assertOk()->assertSee($coverUrl, false);
        $this->get(route('public.books.search', ['search' => 'ACQ-COVER']))->assertOk()->assertSee($coverUrl, false);
        $this->get(route('public.books.show', $book))->assertOk()->assertSee($coverUrl, false);
        $this->get(route('admin.books.edit', $book))
            ->assertOk()
            ->assertSee($coverUrl, false)
            ->assertSee('name="remove_cover_image"', false);

        $updatePayload = $this->bookPayload('ACQ-COVER', $author, $publisher, $category);
        $updatePayload['cover_image'] = UploadedFile::fake()->image('replacement.webp');
        $this->put(route('admin.books.update', $book), $updatePayload)
            ->assertRedirect(route('admin.books.show', $book))
            ->assertSessionHas('success', 'Book updated successfully.');

        $replacementPath = $book->fresh()->cover_image;
        $this->assertNotSame($originalPath, $replacementPath);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($replacementPath);

        $updatePayload = $this->bookPayload('ACQ-COVER', $author, $publisher, $category);
        $updatePayload['remove_cover_image'] = '1';
        $this->put(route('admin.books.update', $book), $updatePayload)
            ->assertRedirect(route('admin.books.show', $book))
            ->assertSessionHas('success', 'Book updated successfully.');

        $this->assertNull($book->fresh()->cover_image);
        Storage::disk('public')->assertMissing($replacementPath);
        $this->get(route('admin.books.index'))->assertSee(asset('images/book-placeholder.svg'));
        $this->get(route('admin.books.show', $book))->assertSee(asset('images/book-placeholder.svg'));
    }

    public function test_book_cover_upload_rejects_invalid_and_oversized_images_without_replacing_existing_cover(): void
    {
        Storage::fake('public');
        [$sabha, $admin] = $this->tenant('COVER-VALID');
        $author = $this->author($sabha, 'Cover Validation Author');
        $publisher = $this->publisher($sabha, 'Cover Validation Publisher');
        $category = $this->category($sabha, '650', 'Cover Validation Category');
        $payload = $this->registrationPayload('ACQ-COVER-INVALID', $author, $publisher, $category) + ['total_copies' => 1];
        $payload['cover_image'] = UploadedFile::fake()->image('too-large.png')->size(2049);

        $this->actingAs($admin)
            ->from(route('admin.books.create'))
            ->post(route('admin.books.store'), $payload)
            ->assertSessionHasErrors('cover_image');
        $this->assertDatabaseMissing('books', ['book_id' => 'ACQ-COVER-INVALID']);
        $this->assertSame([], Storage::disk('public')->allFiles('book-covers'));

        $payload['book_id'] = 'ACQ-COVER-VALID';
        $payload['cover_image'] = UploadedFile::fake()->image('valid.png');
        $this->post(route('admin.books.store'), $payload)->assertRedirect(route('admin.books.index'));
        $book = Book::query()->where('book_id', 'ACQ-COVER-VALID')->firstOrFail();
        $savedPath = $book->cover_image;

        $updatePayload = $this->bookPayload('ACQ-COVER-VALID', $author, $publisher, $category);
        $updatePayload['cover_image'] = UploadedFile::fake()->create('not-an-image.gif', 10, 'image/gif');
        $this->from(route('admin.books.edit', $book))
            ->put(route('admin.books.update', $book), $updatePayload)
            ->assertSessionHasErrors('cover_image');

        $this->assertSame($savedPath, $book->fresh()->cover_image);
        Storage::disk('public')->assertExists($savedPath);
        $this->assertCount(1, Storage::disk('public')->allFiles('book-covers'));
    }

    public function test_typed_author_and_publisher_names_preserve_punctuation_and_are_tenant_local(): void
    {
        [$sabhaA, $adminA] = $this->tenant('TYPED-A');
        [$sabhaB, $adminB] = $this->tenant('TYPED-B');
        $category = $this->category($sabhaA, '657', 'Typed Names Category');
        $authorName = "O'Neil, A. B. (Editor)";
        $publisherName = 'North & South Press, Ltd.';

        $firstPayload = $this->registrationPayload('ACQ-TYPED-1', $this->author($sabhaA, 'Unused'), $this->publisher($sabhaA, 'Unused'), $category);
        $firstPayload['author_name'] = $authorName;
        $firstPayload['publisher_name'] = $publisherName;
        $firstPayload['total_copies'] = 1;

        $this->actingAs($adminA)
            ->post(route('admin.books.store'), $firstPayload)
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book added successfully.');

        $book = Book::query()->where('book_id', 'ACQ-TYPED-1')->firstOrFail();
        $this->assertSame($authorName, $book->author->name);
        $this->assertSame($publisherName, $book->publisher->name);
        $this->assertSame($sabhaA->id, (int) $book->author->pradeshiya_sabha_id);
        $this->assertSame($sabhaA->id, (int) $book->publisher->pradeshiya_sabha_id);

        $secondPayload = $this->registrationPayload('ACQ-TYPED-2', $book->author, $book->publisher, $category) + ['total_copies' => 1];
        $this->post(route('admin.books.store'), $secondPayload)
            ->assertRedirect(route('admin.books.index'));

        $this->assertSame($book->author_id, Book::query()->where('book_id', 'ACQ-TYPED-2')->value('author_id'));
        $this->assertSame($book->publisher_id, Book::query()->where('book_id', 'ACQ-TYPED-2')->value('publisher_id'));

        $this->actingAs($adminB);
        $tenantBPayload = $this->registrationPayload(
            'ACQ-TYPED-B',
            $this->author($sabhaB, 'Temporary Author'),
            $this->publisher($sabhaB, 'Temporary Publisher'),
            $this->category($sabhaB, '658', 'Other Typed Names Category')
        );
        $tenantBPayload['author_name'] = $authorName;
        $tenantBPayload['publisher_name'] = $publisherName;
        $tenantBPayload['total_copies'] = 1;
        $this->post(route('admin.books.store'), $tenantBPayload)
            ->assertRedirect(route('admin.books.index'));

        $bookB = Book::query()->where('book_id', 'ACQ-TYPED-B')->firstOrFail();
        $this->assertNotSame($book->author_id, $bookB->author_id);
        $this->assertNotSame($book->publisher_id, $bookB->publisher_id);
        $this->assertSame($sabhaB->id, (int) $bookB->author->pradeshiya_sabha_id);
        $this->assertSame($sabhaB->id, (int) $bookB->publisher->pradeshiya_sabha_id);
    }

    public function test_invalid_book_submission_returns_to_form_with_field_linked_errors(): void
    {
        [$sabha, $admin] = $this->tenant('INVAL');
        $author = $this->author($sabha, 'Validation Author');
        $publisher = $this->publisher($sabha, 'Validation Publisher');
        $category = $this->category($sabha, '655', 'Validation Category');
        $payload = $this->registrationPayload('ACQ-INVALID', $author, $publisher, $category) + ['total_copies' => 0];
        $payload['ddc_number'] = 'not-a-ddc';
        $payload['author_name'] = '';
        $payload['publisher_name'] = str_repeat('P', 256);

        Log::spy();
        $this->actingAs($admin)
            ->from(route('admin.books.create'))
            ->followingRedirects()
            ->post(route('admin.books.store'), $payload)
            ->assertOk()
            ->assertSee('id="book-form-errors"', false)
            ->assertSee('href="#ddc_number"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="total_copies-error"', false)
            ->assertSee('id="author_name-error"', false)
            ->assertSee('id="publisher_name-error"', false)
            ->assertSee('value="ACQ-INVALID"', false);

        $this->assertDatabaseMissing('books', ['book_id' => 'ACQ-INVALID']);
        $this->assertDatabaseMissing('book_copies', ['accession_number' => 'ACQ-INVALID-001']);
        $this->assertDatabaseMissing('authors', ['name' => '']);
        $this->assertDatabaseMissing('publishers', ['name' => str_repeat('P', 256)]);
        Log::shouldHaveReceived('warning')
            ->with('book_creation_validation_failed', \Mockery::on(
                fn (array $context): bool => $context['tenant_id'] === $sabha->id
                    && in_array('ddc_number', $context['invalid_fields'], true)
                    && in_array('total_copies', $context['invalid_fields'], true)
            ))
            ->once();
    }

    public function test_book_registration_form_uses_tenant_ranges_without_exposing_another_tenant_category(): void
    {
        [$sabha, $admin] = $this->tenant('REGCAT-A');
        [$otherSabha] = $this->tenant('REGCAT-B');
        $author = $this->author($sabha, 'Registration Form Author');
        $publisher = $this->publisher($sabha, 'Registration Form Publisher');
        $tenantCategory = $this->category($sabha, '650', 'Tenant Registration Category');
        $otherCategory = $this->category($otherSabha, '650', 'Other Tenant Registration Category');
        $rangeCategory = $sabha->categories()->where('code', '900')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.books.create'))
            ->assertOk()
            ->assertSee('value="'.$rangeCategory->id.'"', false)
            ->assertSee('value="'.$tenantCategory->id.'"', false)
            ->assertDontSee('value="'.$otherCategory->id.'"', false);

        $payload = $this->bookPayload('ACQ-TENANT-CAT', $author, $publisher, $rangeCategory) + ['total_copies' => 1];
        $payload['ddc_number'] = '900.2';

        $this->post(route('admin.books.store'), $payload)
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book added successfully.');

        $this->assertDatabaseHas('books', [
            'book_id' => 'ACQ-TENANT-CAT',
            'category_id' => $rangeCategory->id,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        $this->assertDatabaseHas('book_copies', [
            'accession_number' => 'ACQ-TENANT-CAT-001',
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
    }

    public function test_database_failure_rolls_back_book_and_copies_and_returns_a_visible_error(): void
    {
        [$sabha, $admin] = $this->tenant('DBERR');
        $author = $this->author($sabha, 'Database Failure Author');
        $publisher = $this->publisher($sabha, 'Database Failure Publisher');
        $category = $this->category($sabha, '656', 'Database Failure Category');
        $payload = $this->registrationPayload('ACQ-DB-FAIL', $author, $publisher, $category) + ['total_copies' => 1];
        $payload['author_name'] = 'Uncommitted Failure Author';
        $payload['publisher_name'] = 'Uncommitted Failure Publisher';

        BookCopy::creating(function (): void {
            throw new QueryException(
                'mysql',
                'insert into `book_copies`',
                [],
                new \PDOException('Simulated database constraint failure.')
            );
        });
        Log::spy();

        $this->actingAs($admin)
            ->from(route('admin.books.create'))
            ->post(route('admin.books.store'), $payload)
            ->assertRedirect(route('admin.books.create'))
            ->assertSessionHas('error', 'The book could not be saved. Please review the details and try again.');

        $this->assertDatabaseMissing('books', ['book_id' => 'ACQ-DB-FAIL']);
        $this->assertDatabaseMissing('book_copies', ['accession_number' => 'ACQ-DB-FAIL-001']);
        $this->assertDatabaseMissing('authors', ['name' => 'Uncommitted Failure Author']);
        $this->assertDatabaseMissing('publishers', ['name' => 'Uncommitted Failure Publisher']);
        $this->get(route('admin.books.create'))
            ->assertSee('The book could not be saved. Please review the details and try again.')
            ->assertSee('alert-danger', false)
            ->assertDontSee('Book added successfully.');
        Log::shouldHaveReceived('error')
            ->with('book_creation_failure', \Mockery::on(
                fn (array $context): bool => $context['user_id'] === $admin->id
                    && $context['tenant_id'] === $sabha->id
                    && $context['exception'] instanceof QueryException
                    && $context['exception']->getPrevious()?->getMessage() === 'Simulated database constraint failure.'
            ))
            ->once();
    }

    public function test_book_creation_is_denied_to_main_admin_and_librarian(): void
    {
        [$sabha, $admin] = $this->tenant('AUTH');
        $mainAdmin = User::factory()->create([
            'role' => User::ROLE_MAIN_ADMIN,
            'is_active' => true,
        ]);
        $librarian = User::factory()->create([
            'role' => User::ROLE_LIBRARIAN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        $this->actingAs($mainAdmin)
            ->get(route('admin.books.index'))
            ->assertOk()
            ->assertDontSee('Add Book');
        $this->get(route('admin.books.create'))->assertForbidden();
        $this->post(route('admin.books.store'), [])->assertForbidden();

        $this->actingAs($librarian)
            ->get(route('admin.books.create'))
            ->assertRedirect(route('librarian.dashboard'));
        $this->post(route('admin.books.store'), [])->assertRedirect(route('librarian.dashboard'));
        $this->assertDatabaseCount('books', 0);
    }

    public function test_sabha_admin_can_update_only_own_books_and_deletion_preserves_copy_history(): void
    {
        [$sabhaA, $adminA] = $this->tenant('UPDA');
        [$sabhaB] = $this->tenant('UPDB');
        $authorA = $this->author($sabhaA, 'Author A');
        $publisherA = $this->publisher($sabhaA, 'Publisher A');
        $categoryA = $this->category($sabhaA, '651', 'Cataloguing A');
        $authorB = $this->author($sabhaB, 'Author B');
        $publisherB = $this->publisher($sabhaB, 'Publisher B');
        $categoryB = $this->category($sabhaB, '652', 'Cataloguing B');
        $bookA = $this->createBook($sabhaA, 'ACQ-UPD-A', 'Own Book', $authorA, $publisherA, $categoryA);
        $bookB = $this->createBook($sabhaB, 'ACQ-UPD-B', 'Other Book', $authorB, $publisherB, $categoryB);
        BookCopy::create([
            'book_id' => $bookA->id,
            'pradeshiya_sabha_id' => $sabhaA->id,
            'accession_number' => 'ACQ-UPD-A-001',
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        $this->actingAs($adminA)
            ->get(route('admin.books.index'))
            ->assertOk()
            ->assertSee('Own Book')
            ->assertSee('Other Book');
        $this->get(route('admin.books.show', $bookA))->assertOk();
        $this->get(route('admin.books.show', $bookB))->assertOk();
        $this->get(route('admin.books.edit', $bookB))->assertNotFound();
        $this->get(route('admin.books.edit', $bookA))->assertOk()->assertDontSee('Description');
        $this->put(route('admin.books.update', $bookB), [])->assertForbidden();
        $this->delete(route('admin.books.destroy', $bookB))->assertNotFound();

        $this->put(route('admin.books.update', $bookA), array_merge($this->bookPayload('ACQ-UPD-A', $authorA, $publisherA, $categoryA), [
            'title' => 'Updated Own Book',
        ]))->assertRedirect(route('admin.books.show', $bookA));
        $this->assertSame('Updated Own Book', $bookA->fresh()->title);

        $this->delete(route('admin.books.destroy', $bookA))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('error');
        $this->assertModelExists($bookA->fresh());
        $this->assertDatabaseHas('book_copies', ['book_id' => $bookA->id, 'accession_number' => 'ACQ-UPD-A-001']);
    }

    public function test_cross_sabha_category_ids_are_rejected(): void
    {
        [$sabhaA, $adminA] = $this->tenant('REF-A');
        [$sabhaB] = $this->tenant('REF-B');
        $authorA = $this->author($sabhaA, 'Author A');
        $publisherA = $this->publisher($sabhaA, 'Publisher A');
        $categoryA = $this->category($sabhaA, '653', 'Cataloguing A');
        $authorB = $this->author($sabhaB, 'Author B');
        $publisherB = $this->publisher($sabhaB, 'Publisher B');
        $categoryB = $this->category($sabhaB, '653', 'Cataloguing B');
        $bookA = $this->createBook($sabhaA, 'ACQ-REF-A', 'Own Book', $authorA, $publisherA, $categoryA);

        $this->actingAs($adminA);
        $payload = $this->registrationPayload('ACQ-REF-CATEGORY', $authorA, $publisherA, $categoryA);
        $payload['category_id'] = $categoryB->id;
        $this->from(route('admin.books.create'))
            ->post(route('admin.books.store'), $payload + ['total_copies' => 1])
            ->assertSessionHasErrors('category_id');

        $update = $this->bookPayload('ACQ-REF-A', $authorA, $publisherA, $categoryA);
        $update['publisher_id'] = $publisherB->id;
        $this->from(route('admin.books.edit', $bookA))
            ->put(route('admin.books.update', $bookA), $update)
            ->assertSessionHasErrors('publisher_id');

        $this->assertDatabaseMissing('books', ['book_id' => 'ACQ-REF-CATEGORY']);
        $this->assertSame('Own Book', $bookA->fresh()->title);
    }

    public function test_author_and_publisher_quick_add_use_one_name_field_and_remain_tenant_local(): void
    {
        [$sabhaA, $adminA] = $this->tenant('QCK-A');
        [$sabhaB, $adminB] = $this->tenant('QCK-B');

        $authorResponse = $this->actingAs($adminA)
            ->postJson(route('admin.authors.store'), ['name' => 'K. J. Kumara'])
            ->assertCreated()
            ->assertJsonPath('name', 'K. J. Kumara');
        $authorId = $authorResponse->json('id');
        $authorA = Author::query()->where('name', 'K. J. Kumara')->firstOrFail();
        $this->assertSame($sabhaA->id, (int) $authorA->pradeshiya_sabha_id);
        $this->assertFalse(Schema::hasColumn('authors', 'first_name'));
        $this->postJson(route('admin.authors.store'), ['name' => 'K. J. Kumara'])->assertUnprocessable();

        $this->actingAs($adminB)
            ->postJson(route('admin.authors.store'), ['name' => 'K. J. Kumara'])
            ->assertCreated();
        $authorB = Author::query()->where('name', 'K. J. Kumara')->firstOrFail();
        $this->assertNotSame($authorA->id, $authorB->id);
        $this->assertSame($sabhaB->id, (int) $authorB->pradeshiya_sabha_id);

        $publisherResponse = $this->actingAs($adminA)
            ->postJson(route('admin.publishers.store'), ['name' => 'Harbor Press'])
            ->assertCreated();
        $publisherId = $publisherResponse->json('id');
        $publisherA = Publisher::query()->where('name', 'Harbor Press')->firstOrFail();
        $this->assertSame($sabhaA->id, (int) $publisherA->pradeshiya_sabha_id);
        $this->postJson(route('admin.publishers.store'), ['name' => 'Harbor Press'])->assertUnprocessable();
        $this->actingAs($adminB)
            ->postJson(route('admin.publishers.store'), ['name' => 'Harbor Press'])
            ->assertCreated();
        $this->assertDatabaseCount('publishers', 2);

        $this->actingAs($adminA)
            ->get(route('admin.books.create'))
            ->assertSee('name="author_name"', false)
            ->assertSee('placeholder="Enter author name"', false)
            ->assertSee('name="publisher_name"', false)
            ->assertSee('placeholder="Enter publisher name"', false)
            ->assertDontSee('value="'.$authorId.'"', false)
            ->assertDontSee('value="'.$publisherId.'"', false);
    }

    public function test_categories_can_be_created_only_under_allowed_tenant_ranges(): void
    {
        [$sabhaA, $adminA] = $this->tenant('RNG-A');
        [$sabhaB, $adminB] = $this->tenant('RNG-B');
        $generalRoot = $sabhaA->categories()->where('code', '000')->firstOrFail();
        $artRoot = $sabhaA->categories()->where('code', '700')->firstOrFail();
        $generalRootB = $sabhaB->categories()->where('code', '000')->firstOrFail();

        $this->actingAs($adminA)
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSee('000-100')
            ->assertSee('100-200')
            ->assertSee('900-1000')
            ->assertDontSee('200-300')
            ->assertDontSee('400-500')
            ->assertDontSee('600-700')
            ->assertDontSee('800-900');

        $this->post(route('admin.categories.store'), [
            'parent_id' => $generalRoot->id,
            'code' => '050',
            'name' => 'Information Services',
        ])->assertRedirect(route('admin.categories.index'));
        $categoryA = Category::query()->where('code', '050')->firstOrFail();
        $this->assertSame($sabhaA->id, (int) $categoryA->pradeshiya_sabha_id);
        $this->assertSame($generalRoot->id, (int) $categoryA->parent_id);

        $this->post(route('admin.categories.store'), [
            'parent_id' => $artRoot->id,
            'code' => '750',
            'name' => 'Art and Recreation',
        ])->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('categories', ['code' => '750', 'name' => 'Art and Recreation', 'pradeshiya_sabha_id' => $sabhaA->id]);

        $this->actingAs($adminB)
            ->post(route('admin.categories.store'), [
                'parent_id' => $generalRootB->id,
                'code' => '050',
                'name' => 'Information Services',
            ])->assertRedirect(route('admin.categories.index'));
        $categoryB = Category::query()->where('code', '050')->firstOrFail();
        $this->assertNotSame($categoryA->id, $categoryB->id);
        $this->assertSame($sabhaB->id, (int) $categoryB->pradeshiya_sabha_id);

        $this->actingAs($adminA)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'parent_id' => $generalRoot->id,
                'code' => '100',
                'name' => 'Disallowed Range',
            ])->assertSessionHasErrors('code');
        $this->post(route('admin.categories.store'), [
            'parent_id' => $generalRootB->id,
            'code' => '150',
            'name' => 'Other Tenant Range Must Not Be Used',
        ])->assertSessionHasErrors('parent_id');

        $this->get(route('admin.books.create'))
            ->assertSee('100-200')
            ->assertSee('Information Services')
            ->assertDontSee('Sabha B Hidden Category');
    }

    public function test_member_cannot_access_catalog_mutations_and_existing_book_copies_remain_protected(): void
    {
        [$sabha, $admin] = $this->tenant('DENY');
        $author = $this->author($sabha, 'Member Blocked Author');
        $publisher = $this->publisher($sabha, 'Member Blocked Publisher');
        $category = $this->category($sabha, '654', 'Member Blocked Category');
        $book = $this->createBook($sabha, 'ACQ-DENY', 'Protected Book', $author, $publisher, $category);
        $member = User::factory()->create(['role' => User::ROLE_MEMBER, 'is_active' => true, 'pradeshiya_sabha_id' => $sabha->id]);

        $this->actingAs($member)
            ->get(route('admin.books.create'))
            ->assertRedirect(route('member.dashboard'));
        $this->postJson(route('admin.authors.store'), ['name' => 'Blocked'])->assertRedirect(route('member.dashboard'));
        $this->postJson(route('admin.publishers.store'), ['name' => 'Blocked'])->assertRedirect(route('member.dashboard'));
        $this->post(route('admin.categories.store'), [])->assertRedirect(route('member.dashboard'));

        BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'accession_number' => 'ACQ-DENY-001',
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);
        $this->actingAs($admin)->delete(route('admin.books.destroy', $book))->assertSessionHas('error');
        $this->assertModelExists($book->fresh());
    }

    private function tenant(string $code): array
    {
        $sabha = PradeshiyaSabha::create(['code' => $code, 'name' => 'Catalog '.$code.' Sabha']);
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

    private function author(PradeshiyaSabha $sabha, string $name): Author
    {
        return Author::create(['name' => $name, 'pradeshiya_sabha_id' => $sabha->id]);
    }

    private function publisher(PradeshiyaSabha $sabha, string $name): Publisher
    {
        return Publisher::create(['name' => $name, 'pradeshiya_sabha_id' => $sabha->id]);
    }

    private function category(PradeshiyaSabha $sabha, string $code, string $name): Category
    {
        return Category::create(['code' => $code, 'name' => $name, 'pradeshiya_sabha_id' => $sabha->id]);
    }

    private function createBook(PradeshiyaSabha $sabha, string $acquisitionNumber, string $title, Author $author, Publisher $publisher, Category $category): Book
    {
        return Book::create([
            'book_id' => $acquisitionNumber,
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => $title,
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '600.1',
            'status' => 'available',
        ]);
    }

    private function bookPayload(string $acquisitionNumber, Author $author, Publisher $publisher, Category $category): array
    {
        return [
            'book_id' => $acquisitionNumber,
            'title' => 'Catalog Test Book',
            'isbn' => null,
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '600.1',
            'publication_year' => null,
            'edition' => null,
            'language' => null,
            'shelf_number' => null,
            'status' => 'available',
        ];
    }

    private function registrationPayload(string $acquisitionNumber, Author $author, Publisher $publisher, Category $category): array
    {
        $payload = $this->bookPayload($acquisitionNumber, $author, $publisher, $category);
        unset($payload['author_id'], $payload['publisher_id']);
        $payload['author_name'] = $author->name;
        $payload['publisher_name'] = $publisher->name;

        return $payload;
    }
}
