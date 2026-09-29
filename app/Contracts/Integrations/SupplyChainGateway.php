<?php

namespace App\Contracts\Integrations;

interface SupplyChainGateway
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCustomer(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveSale(array $payload): array;

    /** @return array<string, mixed> */
    public function postSale(string $saleNumber): array;

    /** @return array<string, mixed> */
    public function approveSale(string $saleNumber): array;
}
