<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sabha_id' => ['nullable', 'integer', Rule::exists('pradeshiya_sabhas', 'id')->where('is_active', true)],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'membership_type_id' => ['nullable', 'integer', 'exists:membership_types,id'],
            'status' => ['nullable', 'string', 'max:40'],
            'payment_status' => ['nullable', 'string', 'in:UNPAID,PAID,WAIVED'],
            'payment_type' => ['nullable', 'string', 'max:50'],
            'fine_type' => ['nullable', 'string', 'max:50'],
            'damage_type' => ['nullable', 'string', 'in:MINOR,MAJOR'],
            'availability' => ['nullable', 'string', 'in:AVAILABLE,ISSUED,OVERDUE,LOST,DAMAGED,REPAIR'],
            'ddc' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', 'max:100'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'registration_from' => ['nullable', 'date'],
            'registration_to' => ['nullable', 'date'],
            'expiry_from' => ['nullable', 'date'],
            'expiry_to' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ([['date_from', 'date_to'], ['registration_from', 'registration_to'], ['expiry_from', 'expiry_to']] as [$from, $to]) {
                if ($this->filled($from) && $this->filled($to) && $this->input($from) > $this->input($to)) {
                    $validator->errors()->add($from, 'The start date must be less than or equal to the end date.');
                }
            }
        });
    }
}
