<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sabhaId = $this->user()?->pradeshiya_sabha_id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('authors', 'name')->where('pradeshiya_sabha_id', $sabhaId)],
            'description' => ['nullable', 'string'],
        ];
    }
}
