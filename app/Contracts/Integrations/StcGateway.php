<?php

namespace App\Contracts\Integrations;

interface StcGateway
{
    /** @return array<string, mixed> */
    public function balance(): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function mutations(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function topUps(array $params): array;

    /** @return array<string, mixed> */
    public function topUpOptions(): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array{details: array<string, mixed>, results: list<array<string, mixed>>}
     */
    public function expressShippingRates(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function instantShippingRates(array $params): array;

    /** @return list<array<string, mixed>> */
    public function expressPickupSchedules(): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array{pickup_number: string, order_id: string, tracking_number: string|null}
     */
    public function createExpressPickup(array $params): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array{order_id: string, tracking_number: string|null, status: int|string|null}
     */
    public function createInstantPickup(array $params): array;

    /** @return array<string, mixed> */
    public function trackExpress(string $orderId): array;
}
