<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordMembershipPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('MAIN_ADMIN', 'PRADESHIYA_SABHA_ADMIN', 'LIBRARIAN');
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'payment_type' => ['required', Rule::in(['REGISTRATION', 'MONTHLY_MEMBERSHIP', 'LATE_RENEWAL'])],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
