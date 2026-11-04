<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleBookCatalogSeeder extends Seeder
{
    private const SAMPLE_DESCRIPTION = 'Sample catalogue placeholder. Bibliographic details are unverified; replace them before using this record as a real edition.';

    private const BOOKS = [
        [
            'title' => 'නියැදි සිංහල කියවීම් පොත 01',
            'language' => 'Sinhala',
            'author' => 'Sample Contributor 01',
            'publisher' => 'Sample Imprint 01',
        ],
        [
            'title' => 'නියැදි සිංහල කතා පොත 02',
            'language' => 'Sinhala',
            'author' => 'Sample Contributor 02',
            'publisher' => 'Sample Imprint 02',
        ],
        [
            'title' => 'Sample English Reading Book 01',
            'language' => 'English',
            'author' => 'Sample Contributor 03',
            'publisher' => 'Sample Imprint 03',
        ],
        [
            'title' => 'Sample English Story Book 02',
            'language' => 'English',
            'author' => 'Sample Contributor 04',
            'publisher' => 'Sample Imprint 04',
        ],
        [
            'title' => 'නියැදි සිංහල දැනුම පොත 03',
            'language' => 'Sinhala',
            'author' => 'Sample Contributor 05',
            'publisher' => 'Sample Imprint 05',
        ],
        [
            'title' => 'Sample English Learning Book 03',
            'language' => 'English',
            'author' => 'Sample Contributor 06',
            'publisher' => 'Sample Imprint 06',
        ],
        [
            'title' => 'නියැදි සිංහල ළමා පොත 04',
            'language' => 'Sinhala',
            'author' => 'Sample Contributor 07',
            'publisher' => 'Sample Imprint 07',
        ],
        [
            'title' => 'Sample English Reference Book 04',
            'language' => 'English',
            'author' => 'Sample Contributor 08',
            'publisher' => 'Sample Imprint 08',
        ],
        [
            'title' => 'නියැදි සිංහල පරිසර පොත 05',
            'language' => 'Sinhala',
            'author' => 'Sample Contributor 09',
            'publisher' => 'Sample Imprint 09',
        ],
        [
            'title' => 'Sample English Community Book 05',
            'language' => 'English',
            'author' => 'Sample Contributor 10',
            'publisher' => 'Sample Imprint 10',
        ],
    ];

    public function run(): void
    {
        $category = DB::table('categories')->whereNull('pradeshiya_sabha_id')->where('code', '800')->first();
        if ($category === null) {
            throw new \RuntimeException('Run the DDC seeder before the sample catalog seeder.');
        }

        DB::transaction(function () use ($category): void {
            foreach (self::BOOKS as $index => $sample) {
                $number = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                $authorId = DB::table('authors')->whereNull('pradeshiya_sabha_id')->where('name', $sample['author'])->value('id');
                if ($authorId === null) {
                    $authorId = DB::table('authors')->insertGetId([
                        'name' => $sample['author'],
                        'description' => 'Placeholder contributor for sample catalog data; verify before use.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $publisherId = DB::table('publishers')->whereNull('pradeshiya_sabha_id')->where('name', $sample['publisher'])->value('id');
                if ($publisherId === null) {
                    $publisherId = DB::table('publishers')->insertGetId([
                        'name' => $sample['publisher'],
                        'address' => 'Placeholder imprint for sample catalog data; verify before use.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $bookId = 'SAMPLE-CATALOG-'.$number;
                if (DB::table('books')->where('book_id', $bookId)->exists()) {
                    continue;
                }

                DB::table('books')->insert([
                    'book_id' => $bookId,
                    'title' => $sample['title'],
                    'isbn' => null,
                    'edition' => null,
                    'author_id' => $authorId,
                    'publisher_id' => $publisherId,
                    'category_id' => $category->id,
                    'ddc_number' => $category->code,
                    'publication_year' => null,
                    'language' => $sample['language'],
                    'description' => self::SAMPLE_DESCRIPTION,
                    'status' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->command?->info('Ten sample catalog records are available globally. No physical copies or library inventory were created.');
    }
}
