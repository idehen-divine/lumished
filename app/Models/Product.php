<?php

namespace App\Models;

use App\Enums\ProductStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'price',
        'compare_at_price',
        'stock_quantity',
        'photo',
        'status',
    ];

    public function casts(): array
    {
        return [
            'status' => ProductStatusEnum::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function scopePublished($query)
    {
        return $query->where('status', ProductStatusEnum::PUBLISHED->name);
    }

    public function scopeOwnedByStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}
