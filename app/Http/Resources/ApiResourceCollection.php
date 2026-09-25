<?php

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApiResourceCollection extends AnonymousResourceCollection
{
    public function toResponse($request): JsonResponse
    {
        $additional = $this->additional;
        $success = $additional['success'] ?? true;
        $message = $additional['message'] ?? null;
        unset($additional['success'], $additional['message']);

        return response()->json([
            'success' => $success,
            'message' => $message,
            ...$additional,
            'data' => $this->resolve($request),
        ]);
    }
}
