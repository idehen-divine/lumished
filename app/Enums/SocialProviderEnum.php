<?php

namespace App\Enums;

enum SocialProviderEnum
{
    case GOOGLE;
    case APPLE;

    /**
     * Get all provider case names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(fn (self $case): string => $case->name, self::cases());
    }
}
