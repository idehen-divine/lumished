<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCode;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * Blocks requests from authenticated users whose email is not yet verified.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()
            || ($request->user() instanceof MustVerifyEmail && ! $request->user()->hasVerifiedEmail())) {
            return response()->json([
                'code' => ResponseCode::PRECONDITION_REQUIRED->value,
                'message' => 'Your email address is not verified.',
                'success' => false,
            ], ResponseCode::PRECONDITION_REQUIRED->value);
        }

        return $next($request);
    }
}
