<?php

namespace App\Http\Requests\Store;

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
        $categoryId = $this->route('id');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', Rule::exists('categories', 'id'), Rule::notIn([$categoryId])],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'A category cannot be its own parent.',
        ];
    }
}
