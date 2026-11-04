<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'unique:members,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nic' => ['required', 'string', 'max:50', 'unique:members,nic'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'membership_type_id' => [
                'required',
                'integer',
                Rule::exists('membership_types', 'id')->where(
                    fn ($query) => $query->whereRaw('upper(name) in (?, ?)', ['CHILDREN', 'ELDERS'])
                ),
            ],
            'pradeshiya_sabha_id' => [
                'required',
                'integer',
                Rule::exists('pradeshiya_sabhas', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'member_id' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'rejected_by' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'The full name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'The email has already been registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The passwords do not match.',
            'nic.required' => 'The NIC field is required.',
            'nic.unique' => 'The NIC has already been registered.',
            'phone.required' => 'The phone field is required.',
            'address.required' => 'The address field is required.',
            'membership_type_id.required' => 'The membership type field is required.',
        ];
    }
}
