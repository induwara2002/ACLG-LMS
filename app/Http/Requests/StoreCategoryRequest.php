<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Scopes\PradeshiyaSabhaScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sabhaId = $this->user()?->pradeshiya_sabha_id;

        return [
            'code' => [
                'required',
                'regex:/^(?:[0-9]{3})(?:\.\d+)?$/',
                'max:10',
                Rule::unique('categories', 'code')->where('pradeshiya_sabha_id', $sabhaId),
                function ($attribute, $value, $fail) {
                    $range = Category::withoutGlobalScope(PradeshiyaSabhaScope::class)
                        ->where(function ($query) {
                            $query->whereNull('pradeshiya_sabha_id')
                                ->orWhere('pradeshiya_sabha_id', $this->user()?->pradeshiya_sabha_id);
                        })
                        ->whereKey($this->input('parent_id'))
                        ->first();

                    if (! $range || ! array_key_exists((string) $range->code, Category::MAIN_DDC_RANGES)) {
                        $fail('Select one of the permitted main DDC ranges.');

                        return;
                    }

                    $rangeStart = (int) $range->code;
                    $ddcNumber = (float) $value;
                    if ($ddcNumber < $rangeStart || $ddcNumber >= $rangeStart + 100 || (string) $value === (string) $range->code) {
                        $fail('The DDC number must be within the selected main range.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where(function ($query) use ($sabhaId) {
                        $query->whereNull('pradeshiya_sabha_id')
                            ->orWhere('pradeshiya_sabha_id', $sabhaId);
                    })
                    ->whereNull('parent_id')
                    ->whereIn('code', array_keys(Category::MAIN_DDC_RANGES))),
            ],
        ];
    }
}
