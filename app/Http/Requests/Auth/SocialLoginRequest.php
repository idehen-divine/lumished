<?php

namespace App\Http\Requests\Auth;

use App\Enums\SocialProviderEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(SocialProviderEnum::names())],
            'access_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'The social provider is required.',
            'provider.in' => 'The selected provider is invalid.',
            'access_token.required' => 'The access token field is required.',
        ];
    }
}
