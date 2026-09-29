<?php

namespace App\Services\Notification;

use App\Mail\MemberCredentialsMail;
use App\Mail\MemberRegistrationApprovedMail;
use App\Mail\MemberRegistrationRejectedMail;
use App\Mail\OrderShippedMail;
use App\Mail\PaymentApprovedMail;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentSubmittedMail;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Trx;
use App\Models\TrxPaymentTransfer;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Number;
use Throwable;

class PartnershipEmailService
{
    public function __construct(private readonly MemberNotificationService $memberNotificationService) {}

    public function sendMemberCredentials(Member $member, string $username, string $password): void
    {
        $member->loadMissing('level');

        $this->send($member->member_email, new MemberCredentialsMail(
            memberName: $member->member_name,
            username: $username,
            password: $password,
            memberCode: $member->member_code,
            memberLevel: $member->level?->member_level_name,
            email: $member->member_email,
        ));
    }

    public function sendRegistrationApproved(
        MemberRegistration $registration,
        Member $member,
        string $username,
        string $password,
    ): void {
        $registration->loadMissing(['level', 'parent']);

        $this->send($member->member_email, new MemberRegistrationApprovedMail(
            memberName: $member->member_name,
            memberCode: $member->member_code,
            memberLevel: $registration->level?->member_level_name ?? 'Mitra',
            sponsorName: $registration->parent?->member_name ?? 'Tanpa Upline',
            username: $username,
            password: $password,
        ));
    }

    public function sendRegistrationRejected(MemberRegistration $registration): void
    {
        $registration->loadMissing('level');

        $this->send($registration->member_registration_email, new MemberRegistrationRejectedMail(
            memberName: $registration->member_registration_name,
            registrationId: sprintf('REG-%06d', $registration->getKey()),
            memberLevel: $registration->level?->member_level_name ?? 'Mitra',
            rejectionReason: $registration->member_registration_note ?: 'Data pendaftaran perlu diperbaiki.',
            verificationUrl: url("/member/network/registrations/{$registration->getKey()}"),
        ));
    }

    public function sendPaymentSubmitted(TrxPaymentTransfer $payment): void
    {
        $payment->loadMissing(['trx.buyer', 'trx.seller']);
        $trx = $payment->trx;
        $buyer = $trx === null ? null : $this->partnerBuyer($trx);
        if ($trx === null || $buyer === null) {
            return;
        }

        $this->memberNotificationService->transactionSeller(
            $trx,
            'Pembayaran Perlu Diverifikasi',
            "Bukti pembayaran pesanan {$trx->trx_code} telah diunggah oleh {$buyer->member_name}. Silakan verifikasi pembayaran.",
            'payment',
        );

        $this->send($buyer->member_email, new PaymentSubmittedMail(
            buyerName: $buyer->member_name,
            orderNumber: $trx->trx_code,
            paymentAmount: $this->formatCurrency($this->paymentAmount($payment, $trx)),
            uploadDate: $this->formatDate($payment->payment_transfer_datetime),
            orderUrl: $this->orderUrl($trx),
        ));
    }

    public function sendPaymentReviewed(TrxPaymentTransfer $payment): void
    {
        $payment->loadMissing('trx.buyer');
        $trx = $payment->trx;
        $buyer = $trx === null ? null : $this->partnerBuyer($trx);
        if ($trx === null || $buyer === null) {
            return;
        }

        $amount = $this->formatCurrency($this->paymentAmount($payment, $trx));
        if ($payment->payment_transfer_approval_status === 'approved') {
            $this->memberNotificationService->transactionBuyer(
                $trx,
                'Pembayaran Disetujui',
                "Pembayaran pesanan {$trx->trx_code} telah disetujui dan pesanan akan diproses.",
                'payment',
            );
            $this->send($buyer->member_email, new PaymentApprovedMail(
                buyerName: $buyer->member_name,
                orderNumber: $trx->trx_code,
                paymentAmount: $amount,
                paymentDate: $this->formatDate($payment->payment_transfer_approval_datetime),
                orderUrl: $this->orderUrl($trx),
            ));

            return;
        }

        if ($payment->payment_transfer_approval_status === 'rejected') {
            $reason = $payment->payment_transfer_note ?: 'Bukti pembayaran belum sesuai.';
            $orderCancelled = $trx->trx_status === 'cancelled';
            $this->memberNotificationService->transactionBuyer(
                $trx,
                'Pembayaran Ditolak',
                $orderCancelled
                    ? "Pembayaran pesanan {$trx->trx_code} ditolak: {$reason} Seluruh rangkaian PO dibatalkan dan alokasi stok dilepas."
                    : "Pembayaran pesanan {$trx->trx_code} ditolak: {$reason} Silakan unggah kembali bukti pembayaran.",
                'payment',
            );
            $this->send($buyer->member_email, new PaymentRejectedMail(
                buyerName: $buyer->member_name,
                orderNumber: $trx->trx_code,
                paymentAmount: $amount,
                rejectionReason: $reason,
                paymentUrl: $this->orderUrl($trx),
                orderCancelled: $orderCancelled,
            ));
        }
    }

    public function sendOrderShipped(Trx $trx): void
    {
        if (! in_array($trx->trx_shipping_method, ['courier_express', 'courier_instant', 'courier_manual'], true)) {
            return;
        }

        $trx->loadMissing([
            'buyer',
            'shippingExpress',
            'shippingInstant',
            'shippingManual',
        ]);
        $buyer = $this->partnerBuyer($trx);
        if ($buyer === null) {
            return;
        }

        $shipping = $this->shippingInformation($trx);
        $this->memberNotificationService->transactionBuyer(
            $trx,
            'Pesanan Dikirim',
            "Pesanan {$trx->trx_code} telah dikirim. Buka detail pesanan untuk melihat informasi pengiriman.",
            'shipping',
        );
        $this->send($buyer->member_email, new OrderShippedMail(
            recipientName: $shipping['recipient_name'] ?: $buyer->member_name,
            orderNumber: $trx->trx_code,
            orderDate: $this->formatDate($trx->trx_datetime),
            shippingDate: $this->formatDate($trx->trx_status_datetime),
            courier: $shipping['courier'],
            trackingNumber: $shipping['tracking_number'],
            shippingAddress: $shipping['address'],
            trackingUrl: $this->orderUrl($trx),
        ));
    }

    private function partnerBuyer(Trx $trx): ?Member
    {
        if (! in_array($trx->trx_buyer_type, ['distributor', 'agent', 'reseller'], true)) {
            return null;
        }

        return $trx->buyer;
    }

    /** @return array{recipient_name: string, courier: string, tracking_number: string, address: string} */
    private function shippingInformation(Trx $trx): array
    {
        if ($trx->trx_shipping_method === 'courier_express' && $trx->shippingExpress) {
            $shipping = $trx->shippingExpress;

            return [
                'recipient_name' => (string) $shipping->shipping_courier_express_destination_name,
                'courier' => $this->courierName([
                    $shipping->shipping_courier_express_expedition_name,
                    $shipping->shipping_courier_express_expedition_service,
                ]),
                'tracking_number' => $shipping->shipping_courier_express_awb ?: '-',
                'address' => $this->address([
                    $shipping->shipping_courier_express_destination_address,
                    $shipping->shipping_courier_express_destination_subdistrict_name,
                    $shipping->shipping_courier_express_destination_district_name,
                    $shipping->shipping_courier_express_destination_city_name,
                    $shipping->shipping_courier_express_destination_province_name,
                    $shipping->shipping_courier_express_destination_zipcode,
                ]),
            ];
        }

        if ($trx->trx_shipping_method === 'courier_instant' && $trx->shippingInstant) {
            $shipping = $trx->shippingInstant;

            return [
                'recipient_name' => (string) $shipping->shipping_courier_instant_destination_name,
                'courier' => $this->courierName([
                    $shipping->shipping_courier_instant_expedition_name,
                    $shipping->shipping_courier_instant_expedition_service,
                ]),
                'tracking_number' => $shipping->shipping_courier_instant_awb ?: '-',
                'address' => $this->address([
                    $shipping->shipping_courier_instant_destination_address,
                    $shipping->shipping_courier_instant_destination_address_note,
                ]),
            ];
        }

        $shipping = $trx->shippingManual;

        return [
            'recipient_name' => (string) $shipping?->shipping_courier_manual_destination_name,
            'courier' => (string) ($shipping?->shipping_courier_manual_name ?: 'Kurir'),
            'tracking_number' => (string) ($shipping?->shipping_courier_manual_awb ?: '-'),
            'address' => $this->address([
                $shipping?->shipping_courier_manual_destination_address,
                $shipping?->shipping_courier_manual_destination_subdistrict_name,
                $shipping?->shipping_courier_manual_destination_district_name,
                $shipping?->shipping_courier_manual_destination_city_name,
                $shipping?->shipping_courier_manual_destination_province_name,
                $shipping?->shipping_courier_manual_destination_zipcode,
            ]),
        ];
    }

    /** @param array<int, mixed> $parts */
    private function courierName(array $parts): string
    {
        return collect($parts)
            ->filter(fn (mixed $part): bool => filled($part))
            ->implode(' - ') ?: 'Kurir';
    }

    /** @param array<int, mixed> $parts */
    private function address(array $parts): string
    {
        return collect($parts)
            ->filter(fn (mixed $part): bool => filled($part))
            ->implode(', ') ?: '-';
    }

    private function paymentAmount(TrxPaymentTransfer $payment, Trx $trx): int
    {
        return (int) ($payment->payment_transfer_amount
            ?: $payment->payment_transfer_bill_amount
            ?: $trx->trx_bill_amount);
    }

    private function formatCurrency(int $amount): string
    {
        return (string) Number::currency($amount, in: 'IDR', locale: 'id', precision: 0);
    }

    private function formatDate(mixed $date): string
    {
        if (! $date instanceof CarbonInterface) {
            return '-';
        }

        return $date->copy()->locale('id')->translatedFormat('d F Y H:i');
    }

    private function orderUrl(Trx $trx): string
    {
        return url("/member/transactions/orders/{$trx->getKey()}");
    }

    private function send(?string $recipient, Mailable $mailable): void
    {
        $recipient = trim((string) $recipient);
        if ($recipient === '') {
            return;
        }

        try {
            Mail::to($recipient)->send($mailable);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
