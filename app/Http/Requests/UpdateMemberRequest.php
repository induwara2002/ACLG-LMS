<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:members,email,' . $this->route('member')->id, Rule::unique('users', 'email')->ignore($this->route('member')->user_id ?? 0)],
            'nic' => ['required', 'string', 'max:50', 'unique:members,nic,' . $this->route('member')->id],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string'],
            'membership_type_id' => [
                'required',
                'integer',
                Rule::exists('membership_types', 'id')->where(
                    fn ($query) => $query->whereRaw('upper(name) in (?, ?)', ['CHILDREN', 'ELDERS'])
                ),
            ],
            'registration_date' => ['nullable', 'date'],
            'membership_start_date' => ['nullable', 'date'],
            'next_renewal_date' => ['nullable', 'date'],
            'status' => [
                'required',
                Rule::in(in_array($this->route('member')?->status, ['PENDING', 'REJECTED', 'SUSPENDED', 'EXPIRED'], true)
                    ? [$this->route('member')->status]
                    : ['ACTIVE', 'EXPIRED', 'SUSPENDED']),
            ],
        ];
    }
}
