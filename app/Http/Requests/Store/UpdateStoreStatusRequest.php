<?php

namespace App\Http\Requests\Store;

use App\Enums\StoreStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = array_map(fn ($case) => $case->name, StoreStatusEnum::cases());

        return [
            'status' => ['required', 'string', Rule::in($statuses)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The status field is required.',
            'status.in' => 'The status must be active or suspended.',
        ];
    }
}
