<?php

namespace App\Traits;

use App\Enums\ResponseCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

        if ($exception instanceof ValidationException) {
            $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                ->setMessage('The given data was invalid.')
                ->setError($exception->errors());

            return $this;
        }

        $this->setCode($code)
            ->setMessage($message);

        if (config('app.debug')) {
            $this->setError($exception->getMessage());
        }

        return $this;
    }
}
