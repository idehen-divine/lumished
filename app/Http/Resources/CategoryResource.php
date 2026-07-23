<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->when($this->products_count !== null, $this->products_count),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
