<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('slug') && ! $this->filled('domain')) {
                $validator->errors()->add('slug', 'Either slug or domain parameter is required.');
                $validator->errors()->add('domain', 'Either slug or domain parameter is required.');
            }
        });
    }
}
