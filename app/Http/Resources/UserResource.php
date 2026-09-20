<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? 'NA',
            'first_name' => $this->first_name ?? 'NA',
            'last_name' => $this->last_name,
            'other_name' => $this->other_name,
            'email' => $this->email ?? 'NA',
            'phone_no' => $this->phone_no,
            'profile_image' => imageHelper()->getUrl($this->profile_image),
            'gender' => $this->gender,
            'status' => $this->status,
            'full_name' => $this->full_name,
            'role' => $this->getRole(),
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'two_factor_confirmed_at' => $this->two_factor_confirmed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
