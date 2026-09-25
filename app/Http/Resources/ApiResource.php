<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiResource extends JsonResource
{
    protected static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }

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
        ], $this->responseStatus());
    }

    private function responseStatus(): int
    {
        return $this->resource instanceof Model && $this->resource->wasRecentlyCreated
            ? 201
            : 200;
    }
}
