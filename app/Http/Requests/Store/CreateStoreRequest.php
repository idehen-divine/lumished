<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'currency' => ['nullable', 'string', 'max:3'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'whatsapp_number' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The store name is required.',
            'name.max' => 'The store name cannot exceed 255 characters.',
            'logo.image' => 'The logo must be a valid image.',
            'logo.mimes' => 'The logo must be a JPEG, PNG, or WebP image.',
            'logo.max' => 'The logo size cannot exceed 5MB.',
            'whatsapp_number.required' => 'The WhatsApp number is required.',
        ];
    }
}
