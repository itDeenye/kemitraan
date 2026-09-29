<?php

namespace App\Exceptions;

use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AccountLockedException extends RuntimeException
{
    public function __construct(private readonly DateTimeInterface $lockedUntil)
    {
        parent::__construct('Akun dikunci sementara.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'process_error',
            'data' => [
                'locked_until' => $this->lockedUntil->format(DATE_ATOM),
                'retry_after' => max(0, $this->lockedUntil->getTimestamp() - now()->timestamp),
            ],
        ], 423);
    }
}
