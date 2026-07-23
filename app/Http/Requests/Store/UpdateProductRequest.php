<?php

namespace App\Http\Requests\Store;

use App\Enums\ProductStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(fn ($case) => $case->name, ProductStatusEnum::cases());

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'category_ids' => ['nullable', 'array', 'min:1'],
            'category_ids.*' => ['string', Rule::exists('categories', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'price.min' => 'The product price must be 0 or a positive number.',
            'photo.max' => 'The main photo size cannot exceed 5MB.',
            'photos.max' => 'You can upload up to 3 extra photos.',
            'photos.*.max' => 'Each extra photo size cannot exceed 5MB.',
        ];
    }
}
