<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BookCatalogImportController extends Controller
{
    private const HEADERS = ['title', 'author', 'isbn', 'category', 'publisher', 'publication_year', 'language'];

    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->isPradeshiyaSabhaAdmin(), 403);

        $validated = $request->validate([
            'catalog_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $handle = fopen($validated['catalog_file']->getRealPath(), 'rb');
        if ($handle === false) {
            return back()->withErrors(['catalog_file' => 'The CSV file could not be read.']);
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);

            return back()->withErrors(['catalog_file' => 'The CSV file must include the required header row.']);
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
        if ($headers !== self::HEADERS) {
            fclose($handle);

            return back()->withErrors(['catalog_file' => 'CSV headers must be: '.implode(',', self::HEADERS).'.']);
        }

        $rows = [];
        $errors = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($rows) >= 1000) {
                $errors['catalog_file'][] = 'CSV imports are limited to 1,000 data rows.';
                break;
            }
            if ($values === [null] || $values === []) {
                continue;
            }

            if (count($values) !== count(self::HEADERS)) {
                $errors['row_'.$rowNumber][] = "Row {$rowNumber} has an incorrect number of columns.";
                continue;
            }

            $row = array_combine(self::HEADERS, array_map(fn ($value) => trim((string) $value), $values));
            $validator = Validator::make($row, [
                'title' => ['required', 'string', 'max:255'],
                'author' => ['required', 'string', 'max:255'],
                'isbn' => ['nullable', 'string', 'max:100'],
                'category' => ['required', 'string', 'max:255'],
                'publisher' => ['required', 'string', 'max:255'],
                'publication_year' => ['nullable', 'integer', 'min:1', 'max:'.now()->year],
                'language' => ['nullable', 'string', 'max:100'],
            ]);
            if ($validator->fails()) {
                $errors['row_'.$rowNumber] = $validator->errors()->all();
                continue;
            }

            $rows[] = $row;
        }
        fclose($handle);

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }
        if ($rows === []) {
            return back()->withErrors(['catalog_file' => 'The CSV file contains no catalog rows.']);
        }

        $seenIsbns = [];
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $categoryExists = Category::withoutGlobalScopes()
                ->where(function ($query) use ($row): void {
                    $query->where('code', $row['category'])
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($row['category'])]);
                })
                ->exists();
            if (! $categoryExists) {
                $errors['row_'.$rowNumber][] = 'No existing category matches "'.$row['category'].'".';
            }

            if ($row['isbn'] !== '') {
                $key = mb_strtolower($row['isbn']);
                $existing = Book::query()->where('isbn', $row['isbn'])->first();
                if (isset($seenIsbns[$key]) && mb_strtolower($seenIsbns[$key]) !== mb_strtolower($row['title'])) {
                    $errors['row_'.$rowNumber][] = 'The ISBN is repeated with a different title in this CSV.';
                } elseif ($existing && mb_strtolower($existing->title) !== mb_strtolower($row['title'])) {
                    $errors['row_'.$rowNumber][] = 'This ISBN is already attached to a different catalog title.';
                }
                $seenIsbns[$key] = $row['title'];
            }
        }

        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $imported = 0;
        $sabhaId = (int) $request->user()->pradeshiya_sabha_id;
        DB::transaction(function () use ($rows, $sabhaId, &$imported): void {
            foreach ($rows as $row) {
                $category = Category::withoutGlobalScopes()
                    ->where(function ($query) use ($row): void {
                        $query->where('code', $row['category'])->orWhereRaw('LOWER(name) = ?', [mb_strtolower($row['category'])]);
                    })
                    ->first();
                if (! $category) {
                    throw new \RuntimeException('A catalog category changed during import. Retry after reviewing the category list.');
                }

                $authorId = $this->sharedAuthorId($row['author']);
                $publisherId = $this->sharedPublisherId($row['publisher']);
                $existingBook = $row['isbn'] !== '' ? Book::query()->where('isbn', $row['isbn'])->first() : null;
                if ($existingBook) {
                    continue;
                }

                do {
                    $catalogCode = 'CAT-'.Str::upper(Str::random(24));
                } while (Book::query()->where('book_id', $catalogCode)->exists());

                Book::create([
                    'book_id' => $catalogCode,
                    'pradeshiya_sabha_id' => $sabhaId,
                    'title' => $row['title'],
                    'isbn' => $row['isbn'] !== '' ? $row['isbn'] : null,
                    'author_id' => $authorId,
                    'publisher_id' => $publisherId,
                    'category_id' => $category->id,
                    'publication_year' => $row['publication_year'] !== '' ? (int) $row['publication_year'] : null,
                    'language' => $row['language'] !== '' ? $row['language'] : null,
                    'ddc_number' => $category->code,
                    'status' => 'available',
                ]);
                $imported++;
            }
        });

        return redirect()->route('admin.books.index')->with('success', "Imported {$imported} catalog records. No physical inventory was created.");
    }

    private function sharedAuthorId(string $name): int
    {
        $authorId = DB::table('authors')->whereNull('pradeshiya_sabha_id')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
        if ($authorId !== null) {
            return (int) $authorId;
        }

        return (int) DB::table('authors')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sharedPublisherId(string $name): int
    {
        $publisherId = DB::table('publishers')->whereNull('pradeshiya_sabha_id')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');
        if ($publisherId !== null) {
            return (int) $publisherId;
        }

        return (int) DB::table('publishers')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}