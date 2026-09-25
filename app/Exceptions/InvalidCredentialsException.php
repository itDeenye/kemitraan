<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Username atau kata sandi tidak sesuai.',
            'error_code' => 'process_error',
            'data' => null,
        ], 401);
    }
}
