<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'uuid'],
            'method' => ['required', 'string', 'in:totp,email'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
