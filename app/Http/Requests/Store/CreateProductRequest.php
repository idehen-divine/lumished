<?php

namespace App\Http\Requests\Store;

use App\Enums\ProductStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(fn ($case) => $case->name, ProductStatusEnum::cases());

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['string', Rule::exists('categories', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The product name is required.',
            'price.required' => 'The product price is required.',
            'price.min' => 'The product price must be 0 or a positive number.',
            'photo.max' => 'The main photo size cannot exceed 5MB.',
            'photos.max' => 'You can upload up to 3 extra photos.',
            'photos.*.max' => 'Each extra photo size cannot exceed 5MB.',
            'category_ids.required' => 'At least one category must be assigned.',
            'category_ids.min' => 'At least one category must be assigned.',
        ];
    }
}
