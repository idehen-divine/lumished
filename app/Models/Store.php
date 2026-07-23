<?php

namespace App\Models;

use App\Enums\StoreStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'tagline',
        'logo_url',
        'currency',
        'phone',
        'email',
        'address',
        'whatsapp_number',
        'status',
    ];

    public function casts(): array
    {
        return [
            'status' => StoreStatusEnum::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Store $store) {
            $slug = Str::slug($store->name);
            $originalSlug = $slug;
            $counter = 1;

            while (static::where('slug', $slug)->exists()) {
                $slug = $originalSlug.'-'.$counter++;
            }

            $store->slug = $slug;
        });
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

    public function scopeOwnedBy($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
