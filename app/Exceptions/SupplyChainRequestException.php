<?php

namespace App\Exceptions;

class SupplyChainRequestException extends ProcessException
{
    /** @param array<string, mixed> $response */
    public function __construct(
        string $message,
        private readonly int $responseStatus,
        private readonly array $response,
    ) {
        parent::__construct($message, 502);
    }

    public function responseStatus(): int
    {
        return $this->responseStatus;
    }

    /** @return array<string, mixed> */
    public function responsePayload(): array
    {
        return $this->response;
    }
}
