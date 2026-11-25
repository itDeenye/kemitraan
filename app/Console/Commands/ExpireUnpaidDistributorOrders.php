<?php

namespace App\Console\Commands;

use App\Models\Trx;
use App\Services\Purchase\PaymentUploadExpiryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('orders:expire-unpaid-distributor')]
#[Description('Batalkan transaksi reguler distributor ke perusahaan yang melewati batas upload pembayaran')]
class ExpireUnpaidDistributorOrders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PaymentUploadExpiryService $expiryService): int
    {
        $expired = 0;
        $failed = 0;
        Trx::query()->where('trx_payment_upload_timeout_enabled', true)
            ->where('trx_type', 'stock')->where('trx_is_preorder', false)
            ->where('trx_buyer_type', 'distributor')->where('trx_seller_type', 'warehouse')
            ->where('trx_status', 'waiting_payment')
            ->where('trx_payment_upload_expires_at', '<=', now())
            ->select('trx_id')->chunkById(100, function ($orders) use ($expiryService, &$expired, &$failed): void {
                foreach ($orders as $order) {
                    try {
                        $expired += (int) $expiryService->expire((int) $order->getKey());
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                        $this->error("Gagal memproses transaksi {$order->getKey()}.");
                    }
                }
            }, 'trx_id');

        $this->info("{$expired} transaksi kedaluwarsa; {$failed} gagal.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
