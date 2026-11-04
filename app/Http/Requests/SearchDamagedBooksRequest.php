<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchDamagedBooksRequest extends FormRequest
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
            'damage_type' => ['nullable', 'string', 'in:MINOR,MAJOR'],
            'payment_status' => ['nullable', 'string', 'in:UNPAID,PAID'],
            'damage_from' => ['nullable', 'date'],
            'damage_to' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('damage_from');
            $to = $this->input('damage_to');

            if ($from !== null && $to !== null && $from > $to) {
                $validator->errors()->add('damage_from', 'The damage date from value must be less than or equal to the damage date to value.');
            }
        });
    }
}
