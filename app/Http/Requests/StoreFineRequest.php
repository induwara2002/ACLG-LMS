<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFineRequest extends FormRequest
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
            'borrowing_id' => ['nullable', 'integer', 'exists:borrowings,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'fine_type' => ['required', Rule::in(['OVERDUE', 'DAMAGE_COMPENSATION', 'LOST_BOOK_COMPENSATION'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
