<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\PradeshiyaSabha;
use App\Models\Publisher;
use Database\Seeders\DDCSeeder;
use Database\Seeders\SampleBookCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleBookCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_catalogue_seeding_is_idempotent_and_skips_inactive_authorities(): void
    {
        $firstAuthority = $this->authority('SEEDA');
        $secondAuthority = $this->authority('SEEDB');
        $inactiveAuthority = $this->authority('SEEDX', false);

        $this->seed(DDCSeeder::class);
        $this->seed(SampleBookCatalogSeeder::class);

        $this->assertSame(10, Book::query()->where('book_id', 'like', 'SAMPLE-CATALOG-%')->count());
        $this->assertSame(0, BookCopy::query()->count());
        $this->assertSame(0, Book::query()->where('pradeshiya_sabha_id', $inactiveAuthority->id)->count());
        $this->assertSame(10, Book::query()->whereNull('isbn')->where('book_id', 'like', 'SAMPLE-CATALOG-%')->count());

        $this->seed(SampleBookCatalogSeeder::class);

        $this->assertSame(10, Book::query()->where('book_id', 'like', 'SAMPLE-CATALOG-%')->count());
        $this->assertSame(0, BookCopy::query()->count());
        $this->assertSame(10, Author::withoutGlobalScopes()->where('name', 'like', 'Sample Contributor %')->count());
        $this->assertSame(10, Publisher::withoutGlobalScopes()->where('name', 'like', 'Sample Imprint %')->count());
    }

    public function test_sample_catalogue_records_and_required_relations_stay_in_their_authority(): void
    {
        $firstAuthority = $this->authority('TENANTA');
        $secondAuthority = $this->authority('TENANTB');
        $this->seed(DDCSeeder::class);

        $existingAuthor = Author::create([
            'name' => 'Existing Catalogue Author',
            'pradeshiya_sabha_id' => $firstAuthority->id,
        ]);
        $existingPublisher = Publisher::create([
            'name' => 'Existing Catalogue Publisher',
            'pradeshiya_sabha_id' => $firstAuthority->id,
        ]);
        $existingCategory = Category::withoutGlobalScopes()->where('code', '800')->whereNull('pradeshiya_sabha_id')->firstOrFail();
        $existingBook = $firstAuthority->books()->create([
            'book_id' => 'TENANTA-EXISTING-001',
            'title' => 'Existing catalogue record',
            'author_id' => $existingAuthor->id,
            'publisher_id' => $existingPublisher->id,
            'category_id' => $existingCategory->id,
            'ddc_number' => '800',
            'description' => 'Must remain unchanged.',
        ]);

        $this->seed(SampleBookCatalogSeeder::class);

        $this->assertSame('Existing catalogue record', $existingBook->fresh()->title);
        $this->assertSame('Must remain unchanged.', $existingBook->fresh()->description);

        $sampleBook = Book::query()->where('book_id', 'SAMPLE-CATALOG-001')->firstOrFail();
        $this->assertNull($sampleBook->pradeshiya_sabha_id);
        $this->assertSame('Sample Contributor 01', $sampleBook->author->name);
        $this->assertSame('Sample Imprint 01', $sampleBook->publisher->name);
        $this->assertSame(10, Book::query()->where('book_id', 'like', 'SAMPLE-CATALOG-%')->count());
    }

    private function authority(string $code, bool $active = true): PradeshiyaSabha
    {
        return PradeshiyaSabha::create([
            'code' => $code,
            'name' => "Sample Test {$code}",
            'is_active' => $active,
        ]);
    }
}
