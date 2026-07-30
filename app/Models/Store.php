<?php

namespace App\Models;

use App\Enums\StoreStatusEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    use HasFactory, HasUuids;

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
        'status',
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

    public function settings(): HasOne
    {
        return $this->hasOne(StoreSettings::class);
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
