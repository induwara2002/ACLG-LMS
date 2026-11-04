<?php

namespace App\Services;

use App\Support\Isbn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleBooksMetadataService
{
    public function lookup(string $isbn): array
    {
        $isbn = Isbn::normalize($isbn);
        $cacheKey = 'google-books.isbn.'.hash('sha256', $isbn);
        $cachedResult = Cache::get($cacheKey);

        if (is_array($cachedResult)) {
            return $cachedResult;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->get('https://www.googleapis.com/books/v1/volumes', [
                    'q' => 'isbn:'.$isbn,
                    'maxResults' => 1,
                    'printType' => 'books',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('google_books_isbn_lookup_connection_failed', [
                'isbn' => $isbn,
                'exception' => $exception,
            ]);

            throw new GoogleBooksLookupException('Google Books is temporarily unavailable.', 0, $exception);
        }

        if (! $response->successful()) {
            Log::warning('google_books_isbn_lookup_upstream_failed', [
                'isbn' => $isbn,
                'status' => $response->status(),
            ]);

            throw new GoogleBooksLookupException('Google Books is temporarily unavailable.');
        }

        $volume = $response->json('items.0.volumeInfo');
        if (! is_array($volume)) {
            $result = [
                'found' => false,
                'message' => 'No matching book was found. You can enter the details manually.',
            ];
        } else {
            $publishedDate = is_string($volume['publishedDate'] ?? null) ? $volume['publishedDate'] : '';
            preg_match('/\A([0-9]{4})/', $publishedDate, $year);

            $result = [
                'found' => true,
                'metadata' => [
                    'isbn' => $isbn,
                    'title' => $this->metadataText($volume['title'] ?? null, 255),
                    'authors' => $this->metadataNames($volume['authors'] ?? []),
                    'publisher' => $this->metadataText($volume['publisher'] ?? null, 255),
                    'publication_year' => isset($year[1]) ? (int) $year[1] : null,
                    'edition' => $this->metadataText($volume['edition'] ?? null, 100),
                    'description' => $this->metadataText($volume['description'] ?? null, 10000),
                    'language' => $this->metadataText($volume['language'] ?? null, 100),
                ],
            ];
        }

        Cache::put($cacheKey, $result, now()->addHours(12));

        return $result;
    }

    private function metadataNames(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $names = [];
        foreach ($values as $value) {
            if (is_string($value)) {
                $name = $this->metadataText($value, 255);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function metadataText(mixed $value, int $maximumLength): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $maximumLength) : '';
    }
}
