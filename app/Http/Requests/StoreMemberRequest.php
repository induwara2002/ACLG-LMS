<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:members,email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'nic' => ['required', 'string', 'max:50', 'unique:members,nic'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string'],
            'membership_type_id' => [
                'required',
                'integer',
                Rule::exists('membership_types', 'id')->where(
                    fn ($query) => $query->whereRaw('upper(name) in (?, ?)', ['CHILDREN', 'ELDERS'])
                ),
            ],
            'sabha_id' => ['nullable', 'integer', Rule::exists('pradeshiya_sabhas', 'id')->where('is_active', true)],
            'registration_date' => ['nullable', 'date'],
            'membership_start_date' => ['nullable', 'date'],
            'next_renewal_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:ACTIVE,EXPIRED,SUSPENDED'],
        ];
    }
}
