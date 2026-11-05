<?php

namespace App\Http\Requests;

use App\Rules\ValidIsbn;
use App\Support\Isbn;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreBookRequest extends FormRequest
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
        return $this->user()?->isPradeshiyaSabhaAdmin() ?? false;
    }

    public function rules(): array
    {
        $sabhaId = $this->user()?->pradeshiya_sabha_id;

        return [
            'book_id' => ['required', 'string', 'max:100', 'unique:books,book_id'],
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:13', new ValidIsbn, Rule::unique('books', 'isbn')],
            'author_id' => ['nullable', 'required_without:author_name', 'integer', Rule::exists('authors', 'id')->where('pradeshiya_sabha_id', $sabhaId)],
            'author_name' => ['nullable', 'required_without:author_id', 'string', 'max:255'],
            'publisher_id' => ['nullable', 'required_without:publisher_name', 'integer', Rule::exists('publishers', 'id')->where('pradeshiya_sabha_id', $sabhaId)],
            'publisher_name' => ['nullable', 'required_without:publisher_id', 'string', 'max:255'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->whereNull('pradeshiya_sabha_id')
                ->orWhere('pradeshiya_sabha_id', $sabhaId))],
            'cover_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'ddc_number' => ['required', 'regex:/^[0-9]{3}(?:\.\d+)?$/'],
            'publication_year' => ['nullable', 'integer', 'min:1'],
            'edition' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:100'],
            'shelf_number' => ['nullable', 'string', 'max:100'],
            'total_copies' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['nullable', 'in:available,unavailable,reserved,archived'],
        ];
    }

    public function messages(): array
    {
        return [
            'isbn.unique' => 'A book with this ISBN already exists. Use its existing catalog record instead of creating a duplicate.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['author' => 'author_name', 'publisher' => 'publisher_name'] as $identifier => $name) {
                if ($this->filled($identifier.'_id') && $this->filled($name)) {
                    $validator->errors()->add($name, 'Provide either an existing '.$identifier.' or a new '.$identifier.' name, not both.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('book_creation_validation_failed', [
            'user_id' => $this->user()?->getAuthIdentifier(),
            'tenant_id' => $this->user()?->pradeshiya_sabha_id,
            'route' => $this->route()?->getName(),
            'invalid_fields' => array_keys($validator->errors()->messages()),
        ]);

        parent::failedValidation($validator);
    }
}
