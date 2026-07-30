<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StoreSettings extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'store_id',
        'domain',
        'slug',
        'product_layout',
        'brand_color',
        'background_color',
    ];

    protected $attributes = [
        'product_layout' => 'default',
        'brand_color' => '#111111',
        'background_color' => '#FFFFFF',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StoreSettings $settings) {
            if (! $settings->slug) {
                $store = $settings->store;
                if ($store) {
                    $settings->slug = Str::slug($store->name);
                }
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
