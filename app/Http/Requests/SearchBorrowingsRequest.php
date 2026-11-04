<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchBorrowingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'status' => ['nullable', 'string', 'in:ISSUED,OVERDUE,RETURNED,LOST,DAMAGED'],
            'issue_from' => ['nullable', 'date'],
            'issue_to' => ['nullable', 'date'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'return_from' => ['nullable', 'date'],
            'return_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:issue_date,due_date,return_date,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $pairs = [
                ['issue_from', 'issue_to'],
                ['due_from', 'due_to'],
                ['return_from', 'return_to'],
            ];

            foreach ($pairs as [$fromKey, $toKey]) {
                $from = $this->input($fromKey);
                $to = $this->input($toKey);

                if ($from !== null && $to !== null && $from > $to) {
                    $validator->errors()->add($fromKey, 'The from date must be less than or equal to the to date.');
                }
            }
        });
    }
}
