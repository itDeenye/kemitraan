<?php

namespace Database\Seeders;

use App\Models\AuditTrail;
use App\Models\BankCompany;
use App\Models\Media;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\Notification;
use App\Models\SiteAdministrator;
use App\Models\Stockist;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\WarehouseStockLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DevelopmentMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $member = Member::query()->findOrFail(1);
        $memberAccount = MemberAccount::query()->where('member_account_member_id', 1)->firstOrFail();
        $administrator = SiteAdministrator::query()->findOrFail(1);
        $location = $this->location();
        $now = now();

        DB::transaction(function () use (
            $administrator,
            $location,
            $member,
            $memberAccount,
            $now,
        ): void {
            $this->removeLegacyDevelopmentWarehouse();
            $this->warehouseDetails($location, $now);
            $this->companyBank();
            $this->stockist($member, $location, $now);
            $this->notifications($member, $now);
            $this->auditTrails($administrator, $now);
            $this->media($memberAccount, $administrator, $now);
        });

        $this->writeMediaFixture();
    }

    /** @param array<string, int> $location */
    private function warehouseDetails(array $location, CarbonInterface $now): void
    {
        $warehouse = Warehouse::query()->findOrFail(1);
        $warehouse->fill([
            ...InitialWarehouseSeeder::warehouseAttributes($location),
            'warehouse_logo' => '/storage/media/development/dny-development.png',
            'warehouse_created_datetime' => $warehouse->warehouse_created_datetime ?? $now,
        ]);
        $warehouse->save();
    }

    private function removeLegacyDevelopmentWarehouse(): void
    {
        $warehouse = Warehouse::query()
            ->whereKey(2)
            ->where('warehouse_name', 'Warehouse Cabang DNY')
            ->first();

        if (! $warehouse) {
            return;
        }

        WarehouseStockLog::query()->where('warehouse_stock_log_warehouse_id', $warehouse->getKey())->delete();
        WarehouseStock::query()->where('warehouse_stock_warehouse_id', $warehouse->getKey())->delete();
        $warehouse->delete();
    }

    private function companyBank(): void
    {
        $bankId = (int) DB::table('ref_bank')->orderBy('bank_id')->value('bank_id');

        BankCompany::query()->updateOrCreate(
            ['bank_company_bank_acc_number' => '880000000001'],
            [
                'bank_company_type' => 'company',
                'bank_company_bank_id' => $bankId,
                'bank_company_bank_acc_name' => 'PT Deenye Berkah Abadi',
                'bank_company_bank_is_active' => 1,
            ],
        );

        BankCompany::query()->updateOrCreate(
            ['bank_company_bank_acc_number' => '880000000002'],
            [
                'bank_company_type' => 'spread_payment',
                'bank_company_bank_id' => $bankId,
                'bank_company_bank_acc_name' => 'DNY Spread Payment',
                'bank_company_bank_is_active' => 1,
            ],
        );
    }

    /** @param array<string, int> $location */
    private function stockist(Member $member, array $location, CarbonInterface $now): void
    {
        Stockist::query()->updateOrCreate(
            ['stockist_name' => 'Stockist Development DNY'],
            [
                'stockist_member_id' => $member->getKey(),
                'stockist_email' => 'stockist@dny.example.test',
                'stockist_address' => 'Jalan Development DNY Nomor 10',
                'stockist_mobilephone' => '+6281400000001',
                'stockist_image' => '/storage/media/development/dny-development.png',
                'stockist_subdistrict_id' => $location['subdistrict_id'],
                'stockist_district_id' => $location['district_id'],
                'stockist_city_id' => $location['city_id'],
                'stockist_province_id' => $location['province_id'],
                'stockist_latitude' => '-7.2575',
                'stockist_longitude' => '112.7521',
                'stockist_note' => 'Data stockist development untuk frontend.',
                'stockist_is_active' => 1,
                'stockist_is_deleted' => 0,
                'stockist_input_datetime' => $now,
            ],
        );
    }

    private function notifications(Member $member, CarbonInterface $now): void
    {
        Notification::query()->updateOrCreate(
            [
                'notification_user_type' => 'member',
                'notification_user_id' => $member->getKey(),
                'notification_title' => 'Stok produk perlu diperiksa',
            ],
            [
                'notification_content' => 'Salah satu stok produk development mendekati batas minimum.',
                'notification_category' => 'stock',
                'notification_ref_table' => 'member_stock',
                'notification_ref_id' => 1,
                'notification_is_read' => 0,
                'notification_read_datetime' => null,
                'notification_created_datetime' => $now->copy()->subHour(),
            ],
        );
    }

    private function auditTrails(SiteAdministrator $administrator, CarbonInterface $now): void
    {
        AuditTrail::query()->updateOrCreate(
            [
                'audittrail_admin_id' => $administrator->getKey(),
                'audittrail_desc' => 'Memperbarui data development warehouse',
            ],
            [
                'audittrail_admin_name' => $administrator->administrator_name,
                'audittrail_menu_name' => 'Warehouse',
                'audittrail_act' => 'update',
                'audittrail_payload' => json_encode(['warehouse_id' => 1], JSON_THROW_ON_ERROR),
                'audittrail_results' => json_encode(['success' => true], JSON_THROW_ON_ERROR),
                'audittrail_ip_address' => '127.0.0.1',
                'audittrail_user_agent' => 'DNY Development Seeder',
                'audittrail_datetime' => $now->copy()->subMinutes(30),
            ],
        );
    }

    private function media(
        MemberAccount $memberAccount,
        SiteAdministrator $administrator,
        CarbonInterface $now,
    ): void {
        foreach ([
            '00000000-0000-4000-8000-000000000001' => $memberAccount,
            '00000000-0000-4000-8000-000000000002' => $administrator,
        ] as $uuid => $uploader) {
            Media::query()->updateOrCreate(
                ['media_uuid' => $uuid],
                [
                    'media_uploader_type' => $uploader->getMorphClass(),
                    'media_uploader_id' => $uploader->getAuthIdentifier(),
                    'media_collection' => 'development',
                    'media_original_name' => 'dny-development.png',
                    'media_original_mime_type' => 'image/png',
                    'media_original_size' => 68,
                    'media_mime_type' => 'image/png',
                    'media_size' => 68,
                    'media_chunk_size' => 68,
                    'media_total_chunks' => 1,
                    'media_uploaded_chunks' => 1,
                    'media_status' => Media::STATUS_READY,
                    'media_disk' => 'public',
                    'media_path' => 'media/development/dny-development.png',
                    'media_checksum' => null,
                    'media_error_message' => null,
                    'media_expires_at' => $now->copy()->addYear(),
                ],
            );
        }
    }

    private function writeMediaFixture(): void
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nKkAAAAASUVORK5CYII=',
            true,
        );

        if ($contents === false) {
            throw new RuntimeException('Fixture media development tidak valid.');
        }

        Storage::disk('public')->put('media/development/dny-development.png', $contents);
    }

    /** @return array{province_id: int, city_id: int, district_id: int, subdistrict_id: int} */
    private function location(): array
    {
        return InitialWarehouseSeeder::warehouseLocation();
    }
}
