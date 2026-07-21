<?php

namespace App\Traits;

use App\Enums\ResponseCode;
use Illuminate\Support\Facades\Log;

trait LogAndRespond
{
    public function logAndRespond(
        \Throwable $exception,
        string $message = 'An unexpected error occurred. Please try again later.',
        int $code = ResponseCode::SERVER_ERROR->value,
    ): static {
        Log::error('API Error ['.request()->path().']: '.$exception->getMessage(), [
            'exception' => $exception,
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'input' => request()->except(['password', 'password_confirmation', 'token']),
        ]);

        $this->setCode($code)
            ->setMessage($message);

        if (config('app.debug')) {
            $this->setError($exception->getMessage());
        }

        return $this;
    }
}
