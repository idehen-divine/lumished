<?php

namespace App\Enums;

enum ResponseCode: int
{
    case SUCCESS = 200;
    case CREATED = 201;
    case ALREADY_REPORTED = 208;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case VALIDATION_ERROR = 422;
    case SERVER_ERROR = 500;

    public function defaultMessage(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::CREATED => 'Resource created successfully.',
            self::ALREADY_REPORTED => 'Resource already completed previously.',
            self::UNAUTHORIZED => 'Unauthorized: Invalid or expired credentials.',
            self::FORBIDDEN => 'Forbidden: You do not have access to this resource.',
            self::NOT_FOUND => 'Resource not found.',
            self::VALIDATION_ERROR => 'Validation failed.',
            self::SERVER_ERROR => 'Internal server error.',
        };
    }
}
