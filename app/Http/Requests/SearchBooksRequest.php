<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'ddc' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', 'max:100'],
            'edition' => ['nullable', 'string', 'max:50'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'publication_year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'publication_year_to' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'shelf_number' => ['nullable', 'string', 'max:50'],
            'availability' => ['nullable', 'string', 'in:AVAILABLE,ISSUED,OVERDUE,LOST,DAMAGED,REPAIR'],
            'sort' => ['nullable', 'string', 'in:title,isbn,publication_year,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('publication_year_from');
            $to = $this->input('publication_year_to');

            if ($from !== null && $to !== null && (int) $from > (int) $to) {
                $validator->errors()->add('publication_year_from', 'The publication year from value must be less than or equal to the publication year to value.');
            }
        });
    }
}
