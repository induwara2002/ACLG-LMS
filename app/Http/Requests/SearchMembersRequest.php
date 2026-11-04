<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'membership_type_id' => ['nullable', 'integer', 'exists:membership_types,id'],
            'status' => ['nullable', 'string', 'in:ACTIVE,EXPIRED,SUSPENDED'],
            'registration_from' => ['nullable', 'date'],
            'registration_to' => ['nullable', 'date'],
            'expiry_from' => ['nullable', 'date'],
            'expiry_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:full_name,registration_date,expiry_date,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('registration_from');
            $to = $this->input('registration_to');
            if ($from !== null && $to !== null && $from > $to) {
                $validator->errors()->add('registration_from', 'The registration date from value must be less than or equal to the registration date to value.');
            }

            $expiryFrom = $this->input('expiry_from');
            $expiryTo = $this->input('expiry_to');
            if ($expiryFrom !== null && $expiryTo !== null && $expiryFrom > $expiryTo) {
                $validator->errors()->add('expiry_from', 'The expiry date from value must be less than or equal to the expiry date to value.');
            }
        });
    }
}
