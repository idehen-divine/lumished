<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'tagline' => $this->tagline,
            'logo_url' => imageHelper()->getUrl($this->logo_url),
            'currency' => $this->currency,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'whatsapp_number' => $this->whatsapp_number,
            'domain' => $this->domain,
            'slug' => $this->slug,
            'product_layout' => $this->product_layout,
            'brand_color' => $this->brand_color,
            'background_color' => $this->background_color,
            'status' => $this->status?->name,
            'products_count' => $this->when($this->products_count !== null, $this->products_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
