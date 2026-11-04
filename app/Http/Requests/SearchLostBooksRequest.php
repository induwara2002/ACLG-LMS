<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchLostBooksRequest extends FormRequest
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
            'borrowing_id' => ['nullable', 'integer', 'exists:borrowings,id'],
            'payment_status' => ['nullable', 'string', 'in:UNPAID,PAID'],
            'lost_from' => ['nullable', 'date'],
            'lost_to' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('lost_from');
            $to = $this->input('lost_to');

            if ($from !== null && $to !== null && $from > $to) {
                $validator->errors()->add('lost_from', 'The lost date from value must be less than or equal to the lost date to value.');
            }
        });
    }
}
