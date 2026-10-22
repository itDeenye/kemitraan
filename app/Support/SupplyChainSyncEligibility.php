<?php

namespace App\Support;

final class SupplyChainSyncEligibility
{
    public static function member(int $status, ?string $customerNumber): bool
    {
        return $status === 1 && blank($customerNumber);
    }

    public static function sale(string $sellerType, string $buyerType, string $type, string $status, ?string $invoiceNumber): bool
    {
        return $sellerType === 'warehouse'
            && in_array($buyerType, ['distributor', 'agent', 'reseller'], true)
            && $type === 'stock'
            && $status === 'processing'
            && blank($invoiceNumber);
    }
}
