<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'exists:books,id'],
            'accession_number' => ['required', 'string', 'max:100', 'unique:book_copies,accession_number,' . $this->route('bookCopy')->id],
            'status' => ['required', 'in:AVAILABLE,ISSUED,LOST,DAMAGED,REPAIR'],
            'condition' => ['required', 'in:GOOD,FAIR,DAMAGED'],
            'acquisition_date' => ['required', 'date'],
        ];
    }
}
