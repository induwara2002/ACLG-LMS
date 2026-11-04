<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Scopes\PradeshiyaSabhaScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sabhaId = $this->user()?->pradeshiya_sabha_id;
        $category = $this->route('category');

        return [
            'code' => [
                'required',
                'regex:/^(?:[0-9]{3})(?:\.\d+)?$/',
                'max:10',
                Rule::unique('categories', 'code')->where('pradeshiya_sabha_id', $sabhaId)->ignore($category->id),
                function ($attribute, $value, $fail) use ($category, $sabhaId) {
                    if ((string) $value === (string) $category->code && ! $this->has('parent_id')) {
                        return;
                    }

                    $range = Category::withoutGlobalScope(PradeshiyaSabhaScope::class)
                        ->where(function ($query) use ($sabhaId) {
                            $query->whereNull('pradeshiya_sabha_id')
                                ->orWhere('pradeshiya_sabha_id', $sabhaId);
                        })
                        ->whereKey($this->input('parent_id', $category->parent_id))
                        ->first();

                    $rangeStart = $range ? (int) $range->code : -1;
                    $ddcNumber = (float) $value;
                    if (! $range || ! array_key_exists((string) $range->code, Category::MAIN_DDC_RANGES)
                        || $ddcNumber < $rangeStart
                        || $ddcNumber >= $rangeStart + 100
                        || (string) $value === (string) $range->code) {
                        $fail('The DDC number must be within one of the permitted main ranges.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query
                ->where(function ($query) use ($sabhaId) {
                    $query->whereNull('pradeshiya_sabha_id')
                        ->orWhere('pradeshiya_sabha_id', $sabhaId);
                })
                ->whereNull('parent_id')
                ->whereIn('code', array_keys(Category::MAIN_DDC_RANGES)))],
        ];
    }
}
