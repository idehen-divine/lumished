<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function (Model $model) {
            $slug = $model->slug ?: Str::slug($model->{$model->slugSource()});

            while ($model->slugExists($slug)) {
                $slug .= '-'.Str::lower(Str::random(6, '0123456789'));
            }

            $model->slug = $slug;
        });
    }

    /**
     * The attribute used as the slug source when no slug is provided.
     */
    protected function slugSource(): string
    {
        return 'name';
    }

    /**
     * The column scoping slug uniqueness (e.g. store_id), or null for global uniqueness.
     */
    protected function slugScope(): ?string
    {
        return null;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::where('slug', $slug);

        if ($scope = $this->slugScope()) {
            $query->where($scope, $this->{$scope});
        }

        return $query->exists();
    }
}
