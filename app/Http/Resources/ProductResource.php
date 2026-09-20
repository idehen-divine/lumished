<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => moneyHelper()->fromMinor($this->price),
            'compare_at_price' => moneyHelper()->fromMinor($this->compare_at_price),
            'stock_quantity' => $this->stock_quantity,
            'photo' => imageHelper()->getUrl($this->photo),
            'status' => $this->status?->name,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'store' => new StoreResource($this->whenLoaded('store')),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
