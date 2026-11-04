<?php

namespace App\Http\Requests;

use App\Rules\ValidIsbn;
use App\Support\Isbn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('isbn'))) {
            $this->merge(['isbn' => Isbn::normalize($this->input('isbn'))]);
        }

        if ($this->filled('status')) {
            $this->merge(['status' => strtolower((string) $this->input('status'))]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $book = $this->route('book');

        return $user?->isPradeshiyaSabhaAdmin()
            && $book instanceof \App\Models\Book
            && (int) $book->pradeshiya_sabha_id === (int) $user->pradeshiya_sabha_id;
    }

    public function rules(): array
    {
        $sabhaId = $this->user()?->pradeshiya_sabha_id;

        return [
            'book_id' => ['required', 'string', 'max:100', 'unique:books,book_id,'.$this->route('book')->id],
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:13', new ValidIsbn, Rule::unique('books', 'isbn')->ignore($this->route('book')->id)],
            'author_id' => ['required', Rule::exists('authors', 'id')->where('pradeshiya_sabha_id', $sabhaId)],
            'publisher_id' => ['required', Rule::exists('publishers', 'id')->where('pradeshiya_sabha_id', $sabhaId)],
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->whereNull('pradeshiya_sabha_id')
                ->orWhere('pradeshiya_sabha_id', $sabhaId))],
            'cover_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_cover_image' => ['nullable', 'boolean'],
            'ddc_number' => ['required', 'regex:/^[0-9]{3}(?:\.\d+)?$/'],
            'publication_year' => ['nullable', 'integer', 'min:1'],
            'edition' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:100'],
            'shelf_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:available,unavailable,reserved,archived'],
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.unique' => 'A book with this ISBN already exists.',
        ];
    }
}
