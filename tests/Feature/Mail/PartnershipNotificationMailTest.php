<?php

namespace Tests\Feature\Mail;

use App\Mail\MemberCredentialsMail;
use App\Mail\MemberRegistrationApprovedMail;
use App\Mail\MemberRegistrationRejectedMail;
use App\Mail\OrderShippedMail;
use App\Mail\PasswordResetMail;
use App\Mail\PaymentApprovedMail;
use App\Mail\PaymentRejectedMail;
use App\Mail\PaymentSubmittedMail;
use App\Mail\StockScreeningApprovedMail;
use App\Models\Member;
use App\Models\Trx;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

class PartnershipNotificationMailTest extends TestCase
{
    public function test_all_partnership_notification_templates_render_reference_content(): void
    {
        $screenedTransaction = new Trx([
            'trx_code' => 'TRX/CMP/DST/000001/ABC123',
            'trx_is_preorder' => true,
            'trx_bill_amount' => 150000,
        ]);
        $screenedTransaction->setAttribute('trx_id', 1);
        $screenedTransaction->setRelation('buyer', new Member([
            'member_name' => 'Distributor DNY',
        ]));

        $mailables = [
            [
                new MemberCredentialsMail(
                    'Mitra Baru',
                    '0001/0000/0000',
                    'Sementara123',
                    'https://example.test/member/login',
                    '0001/0000/0000',
                    'Distributor',
                    'mitra@example.test',
                ),
                '[DNY Skincare Kemitraan] Registrasi Kemitraan Berhasil – Informasi Akun Anda',
                ['Registrasi Kemitraan Berhasil', 'Distributor', 'mitra@example.test', 'Sementara123'],
            ],
            [
                new MemberRegistrationApprovedMail(
                    'Mitra Disetujui',
                    '0001/0001/0000',
                    'Agent',
                    'Distributor Utama',
                    '0001/0001/0000',
                    'Approve123',
                    'https://example.test/member/login',
                ),
                '[DNY Skincare Kemitraan] Registrasi Kemitraan Anda Telah Disetujui',
                ['Registrasi Kemitraan Disetujui', 'AKTIF', 'Distributor Utama', 'Approve123'],
            ],
            [
                new MemberRegistrationRejectedMail(
                    'Mitra Perbaikan',
                    'REG-000123',
                    'Reseller',
                    'Foto identitas tidak terbaca.',
                    'https://example.test/member/network/registrations/123',
                ),
                '[DNY Skincare Kemitraan] Status Registrasi Kemitraan – Memerlukan Perbaikan',
                ['Registrasi Memerlukan Perbaikan', 'REG-000123', 'Foto identitas tidak terbaca.'],
            ],
            [
                new OrderShippedMail(
                    'Penerima Pesanan',
                    'TRX/CMP/DST/000001/ABC123',
                    '01 September 2026 10:00',
                    '02 September 2026 11:00',
                    'JNE - YES',
                    'AWB123',
                    'Jalan DNY Nomor 1, Surabaya',
                    'https://example.test/member/transactions/orders/1',
                ),
                '[DNY Skincare Kemitraan] Pesanan Anda Telah Dikirim – TRX/CMP/DST/000001/ABC123',
                ['Pesanan Anda Telah Dikirim', 'JNE - YES', 'AWB123', 'Jalan DNY Nomor 1'],
            ],
            [
                new PaymentSubmittedMail(
                    'Pembeli Mitra',
                    'TRX-001',
                    'Rp150.000',
                    '02 September 2026 12:00',
                    'https://example.test/member/transactions/orders/1',
                ),
                '[DNY Skincare Kemitraan] Bukti Pembayaran Berhasil Diterima – TRX-001',
                ['Bukti Pembayaran Berhasil Diterima', 'Rp150.000', 'MENUNGGU VERIFIKASI'],
            ],
            [
                new PaymentApprovedMail(
                    'Pembeli Mitra',
                    'TRX-001',
                    'Rp150.000',
                    '02 September 2026 13:00',
                    'https://example.test/member/transactions/orders/1',
                ),
                '[DNY Skincare Kemitraan] Pembayaran Berhasil Diverifikasi – TRX-001',
                ['Pembayaran Berhasil Diverifikasi', 'Rp150.000', 'LUNAS'],
            ],
            [
                new PaymentRejectedMail(
                    'Pembeli Mitra',
                    'TRX-001',
                    'Rp150.000',
                    'Nominal pada bukti tidak sesuai.',
                    'https://example.test/member/transactions/orders/1',
                ),
                '[DNY Skincare Kemitraan] Verifikasi Pembayaran Memerlukan Perhatian – TRX-001',
                ['Verifikasi Pembayaran Memerlukan Perhatian', 'DITOLAK', 'Nominal pada bukti tidak sesuai.'],
            ],
            [
                new PasswordResetMail(
                    'Mitra DNY',
                    'https://example.test/member/reset-password/token',
                    60,
                    'member',
                ),
                'Reset Password Akun DNY Skincare',
                ['Reset Password', 'Mitra DNY', 'Buat Password Baru', '60 menit'],
            ],
            [
                new StockScreeningApprovedMail($screenedTransaction),
                'Screening Stok Pesanan Disetujui',
                ['Screening Stok Disetujui', 'TRX/CMP/DST/000001/ABC123', 'PO', 'Rp150.000'],
            ],
        ];

        foreach ($mailables as [$mailable, $expectedSubject, $expectedTexts]) {
            $this->assertInstanceOf(Mailable::class, $mailable);
            $this->assertSame($expectedSubject, $mailable->envelope()->subject);
            $html = $mailable->render();
            $this->assertStringContainsString('logo-email.png', $html);
            $this->assertStringContainsString('background:#a90028', $html);
            $this->assertStringContainsString('Email ini dikirim otomatis oleh sistem DNY Skincare Kemitraan.', $html);
            $this->assertStringNotContainsString('Nomor Invoice', $html);

            foreach ($expectedTexts as $expectedText) {
                $this->assertStringContainsString($expectedText, $html);
            }
        }
    }
}
