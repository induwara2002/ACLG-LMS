<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\BookCopy;
use App\Rules\ValidIsbn;
use App\Services\GoogleBooksLookupException;
use App\Services\GoogleBooksMetadataService;
use App\Support\Isbn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\Storage;

class AdminBookController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $books = Book::with(['author', 'publisher', 'category'])
            ->withCount('copies')
            ->withCount(['copies as available_copies_count' => fn ($query) => $query->where('status', 'AVAILABLE')])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('book_id', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhereHas('author', function ($authorQuery) use ($search) {
                            $authorQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('publisher', function ($publisherQuery) use ($search) {
                            $publisherQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.books.index', compact('books', 'search'));
    }

    public function create()
    {
        return view('admin.books.create', [
            'authors' => Author::orderBy('name')->get(),
            'publishers' => Publisher::orderBy('name')->get(),
            'categories' => Category::orderBy('code')->get(),
        ]);
    }

    public function lookupIsbn(Request $request, GoogleBooksMetadataService $googleBooks)
    {
        $validated = $request->validate([
            'isbn' => ['required', 'string', 'max:20', new ValidIsbn],
            'current_book_id' => ['nullable', 'integer'],
        ]);
        $isbn = Isbn::normalize($validated['isbn']);

        $existingBooks = Book::query()->where('isbn', $isbn);
        $currentBookId = (int) ($validated['current_book_id'] ?? 0);
        if ($currentBookId !== 0 && Book::query()->whereKey($currentBookId)->exists()) {
            $existingBooks->whereKeyNot($currentBookId);
        }

        $existingBook = $existingBooks->first();
        if ($existingBook) {
            return response()->json([
                'found' => true,
                'existing_book' => [
                    'title' => $existingBook->title,
                    'book_id' => $existingBook->book_id,
                ],
            ]);
        }

        try {
            return response()->json($googleBooks->lookup($isbn));
        } catch (GoogleBooksLookupException) {

            return response()->json([
                'message' => __('Metadata lookup is temporarily unavailable. You can enter the book details manually.'),
            ], 502);
        }


        if (! is_array($metadata)) {
            return response()->json([
                'found' => false,
                'message' => __('No metadata was found. Enter the book details manually.'),
            ]);
        }

        $publicationDate = is_string($metadata['publish_date'] ?? null) ? $metadata['publish_date'] : '';
        preg_match('/\b([0-9]{4})\b/', $publicationDate, $year);

        return response()->json([
            'found' => true,
            'metadata' => [
                'isbn' => $isbn,
                'title' => $this->metadataText($metadata['title'] ?? null, 255),
                'authors' => $this->metadataNames($metadata['authors'] ?? []),
                'publishers' => $this->metadataNames($metadata['publishers'] ?? []),
                'publication_year' => isset($year[1]) ? (int) $year[1] : null,
                'language' => $this->metadataLanguage($metadata['languages'] ?? []),
            ],
        ]);
    }

    public function store(StoreBookRequest $request)
    {
        $data = $request->validated();
        $copyCount = (int) $data['total_copies'];
        $coverImage = $data['cover_image'] ?? null;
        unset($data['total_copies'], $data['cover_image']);
        $user = $request->user();
        $tenantId = (int) $user->pradeshiya_sabha_id;
        $coverImagePath = null;

        Log::debug('book_creation_controller_reached', [
            'user_id' => $user->getAuthIdentifier(),
            'tenant_id' => $tenantId,
            'route' => $request->route()?->getName(),
        ]);

        try {
            if ($coverImage instanceof UploadedFile) {
                $coverImagePath = $this->storeCoverImage($coverImage);
                $data['cover_image'] = $coverImagePath;
            }

            [$book, $persistedCopyCount] = DB::transaction(function () use ($data, $copyCount, $tenantId) {
                $book = Book::create($data);

                for ($copyNumber = 1; $copyNumber <= $copyCount; $copyNumber++) {
                    BookCopy::create([
                        'book_id' => $book->id,
                        'pradeshiya_sabha_id' => $tenantId,
                        'accession_number' => $book->book_id.'-'.str_pad((string) $copyNumber, 3, '0', STR_PAD_LEFT),
                        'status' => 'AVAILABLE',
                        'condition' => 'GOOD',
                        'acquisition_date' => now()->toDateString(),
                    ]);
                }

                $persistedCopyCount = BookCopy::query()
                    ->where('book_id', $book->getKey())
                    ->where('pradeshiya_sabha_id', $tenantId)
                    ->count();

                if ($persistedCopyCount !== $copyCount) {
                    throw new RuntimeException('Book creation did not persist the expected tenant records.');
                }

                return [$book, $persistedCopyCount];
            });
        } catch (Throwable $exception) {
            $this->deleteCoverImage($coverImagePath, null);
            Log::error('book_creation_failure', [
                'user_id' => $user->getAuthIdentifier(),
                'tenant_id' => $tenantId,
                'sql_state' => $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null,
                'exception' => $exception,
            ]);

            return back()
                ->withInput()
                ->with('error', __('The book could not be saved. Please review the details and try again.'));
        }

        Log::info('book_creation_persisted', [
            'user_id' => $user->getAuthIdentifier(),
            'tenant_id' => $tenantId,
            'book_record_id' => $book->getKey(),
            'persisted_copy_count' => $persistedCopyCount,
        ]);

        return redirect()->route('admin.books.index')->with('success', 'Book added successfully.');
    }

    public function show(Book $book)
    {
        $book->load([
            'author',
            'publisher',
            'category',
            'copies',
            'copies.borrowings' => fn ($query) => $query->whereNull('return_date')->with('member'),
        ]);

        return view('admin.books.show', compact('book'));
    }

    public function edit(Book $book)
    {
        $this->authorizeCatalogMutation($book);
        $book->load(['author', 'publisher', 'category']);

        return view('admin.books.edit', [
            'book' => $book,
            'authors' => Author::orderBy('name')->get(),
            'publishers' => Publisher::orderBy('name')->get(),
            'categories' => Category::orderBy('code')->get(),
        ]);
    }

    public function update(UpdateBookRequest $request, Book $book)
    {
        $this->authorizeCatalogMutation($book);
        $data = $request->validated();
        $coverImage = $data['cover_image'] ?? null;
        $removeCoverImage = (bool) ($data['remove_cover_image'] ?? false);
        unset($data['cover_image'], $data['remove_cover_image']);

        $oldCoverImagePath = $book->cover_image;
        $newCoverImagePath = null;

        try {
            if ($coverImage instanceof UploadedFile) {
                $newCoverImagePath = $this->storeCoverImage($coverImage);
                $data['cover_image'] = $newCoverImagePath;
            } elseif ($removeCoverImage) {
                $data['cover_image'] = null;
            }

            $book->update($data);
        } catch (Throwable $exception) {
            $this->deleteCoverImage($newCoverImagePath, (int) $book->getKey());
            Log::error('book_update_failure', [
                'book_record_id' => $book->getKey(),
                'tenant_id' => $request->user()?->pradeshiya_sabha_id,
                'exception' => $exception,
            ]);

            return back()
                ->withInput()
                ->with('error', __('The book could not be updated. Please review the details and try again.'));
        }

        if (($newCoverImagePath !== null || $removeCoverImage)
            && $oldCoverImagePath !== $newCoverImagePath) {
            $this->deleteCoverImage($oldCoverImagePath, (int) $book->getKey());
        }

        return redirect()->route('admin.books.show', $book)->with('success', 'Book updated successfully.');
    }

    public function destroy(Book $book)
    {
        $this->authorizeCatalogMutation($book);
        if ($book->copies()->exists() || $book->borrowings()->exists() || $book->lostBooks()->exists()) {
            return redirect()->route('admin.books.index')->with('error', 'Cannot delete this book because book copies or historical records exist.');
        }

        $coverImagePath = $book->cover_image;
        $book->delete();
        $this->deleteCoverImage($coverImagePath, (int) $book->getKey());

        return redirect()->route('admin.books.index')->with('success', 'Book deleted successfully.');
    }

    private function authorizeCatalogMutation(Book $book): void
    {
        $user = request()->user();
        abort_unless($user?->isPradeshiyaSabhaAdmin(), 403);
        abort_unless((int) $book->pradeshiya_sabha_id === (int) $user->pradeshiya_sabha_id, 404);
    }

    private function metadataNames(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $names = [];
        foreach ($values as $value) {
            if (is_array($value) && is_string($value['name'] ?? null)) {
                $name = $this->metadataText($value['name'], 255);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    private function metadataLanguage(mixed $values): ?string
    {
        if (! is_array($values) || ! is_array($values[0] ?? null) || ! is_string($values[0]['key'] ?? null)) {
            return null;
        }

        $language = basename($values[0]['key']);
        $knownLanguages = [
            'eng' => 'English',
            'sin' => 'Sinhala',
            'tam' => 'Tamil',
            'fre' => 'French',
            'ger' => 'German',
            'spa' => 'Spanish',
        ];

        return $knownLanguages[$language] ?? null;
    }

    private function metadataText(mixed $value, int $maximumLength): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $maximumLength) : '';
    }

    private function storeCoverImage(UploadedFile $image): string
    {
        $path = $image->store('book-covers', 'public');
        if (! is_string($path) || ! Book::isSafeCoverImagePath($path)) {
            $this->deleteCoverImage(is_string($path) ? $path : null, null);
            throw new RuntimeException('The uploaded book cover could not be stored safely.');
        }

        return $path;
    }

    private function deleteCoverImage(?string $path, ?int $bookId): void
    {
        if ($path === null) {
            return;
        }

        if (! Book::isSafeCoverImagePath($path)) {
            Log::warning('book_cover_delete_rejected', ['book_record_id' => $bookId]);
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($path) && ! $disk->delete($path)) {
            Log::error('book_cover_delete_failed', [
                'book_record_id' => $bookId,
                'cover_path' => $path,
            ]);
        }
    }
}
