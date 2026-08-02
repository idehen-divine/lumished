<?php

namespace App\Models;

use App\Enums\StoreStatusEnum;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory, HasSlug, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'tagline',
        'logo_url',
        'currency',
        'phone',
        'email',
        'address',
        'whatsapp_number',
        'domain',
        'slug',
        'product_layout',
        'brand_color',
        'background_color',
        'status',
    ];

    protected $attributes = [
        'product_layout' => 'default',
        'brand_color' => '#111111',
        'background_color' => '#FFFFFF',
    ];

    public function casts(): array
    {
        return [
            'status' => StoreStatusEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', StoreStatusEnum::ACTIVE->name);
    }
}
