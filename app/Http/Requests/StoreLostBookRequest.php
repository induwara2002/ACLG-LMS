<?php

namespace App\Http\Requests;

use App\Models\Borrowing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLostBookRequest extends FormRequest
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
            'borrowing_id' => ['required', 'integer', 'exists:borrowings,id', function ($attribute, $value, $fail) {
                $borrowing = Borrowing::find($value);
                if (! $borrowing) {
                    return;
                }

                if ((int) $this->input('member_id') !== (int) $borrowing->member_id) {
                    $fail('The selected borrowing does not belong to the selected member.');
                }

                if ($this->filled('book_copy_id') && (int) $this->input('book_copy_id') !== (int) $borrowing->book_copy_id) {
                    $fail('The selected copy does not belong to the selected borrowing.');
                }
            }],
            'book_copy_id' => ['required', 'integer', 'exists:book_copies,id'],
            'lost_date' => ['required', 'date'],
            'compensation_amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
