<?php

namespace Tests\Feature\Api\V1\Partnership;

use App\Jobs\SyncSupplyChainMember;
use App\Mail\MemberCredentialsMail;
use App\Mail\MemberRegistrationApprovedMail;
use App\Mail\MemberRegistrationRejectedMail;
use App\Models\Media;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;
use App\Models\MemberRegistration;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Services\Dashboard\AdminActionSummaryService;
use App\Services\Partnership\MemberCodeService;
use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_retry_after_edit_uses_the_latest_registration_phone_and_ktp(): void
    {
        $registration = $this->pendingDistributorRegistration();
        Http::fake([
            'registration-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'registration-supply.test/*/tambah-customer' => Http::sequence()
                ->push(['success' => false, 'message' => 'Nomor telepon sudah terdaftar.'], 422)
                ->push(['success' => true, 'data' => ['cust_no' => '90000046']]),
        ]);
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $this->postJson($url.'/approve')->assertStatus(502);
        $this->assertPendingApprovalHasNoMember($registration);
        $this->patchJson($url, ['mobile_phone' => '081799998888', 'identity_no' => '0001234567890124'])
            ->assertOk()->assertJsonPath('data.status.code', 'requested');

        $this->postJson($url.'/approve')->assertOk()->assertJsonPath('data.status.code', 'approved');
        $requests = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/tambah-customer'))->values();
        $this->assertCount(2, $requests);
        $this->assertSame('+6281234567890', $requests[0][0]['cust_phone1']);
        $this->assertSame('0001234567890123', $requests[0][0]['NoKTP']);
        $this->assertSame('+6281799998888', $requests[1][0]['cust_phone1']);
        $this->assertSame('0001234567890124', $requests[1][0]['NoKTP']);
        $member = Member::query()->findOrFail($registration->fresh()->member_registration_member_id);
        $this->assertSame('+6281799998888', $member->member_mobilephone);
        $this->assertSame('0001234567890124', $member->member_identity_no);
        $this->assertSame('90000046', $member->member_supply_chain_code);
        $this->assertDatabaseCount('member', 1);
        $this->assertDatabaseCount('member_account', 1);
    }

    public function test_registration_and_edit_reject_a_phone_stored_in_legacy_local_format(): void
    {
        $registration = $this->pendingDistributorRegistration();
        [$member] = $this->createMemberAccount('DST', '0001/0000/0000', 'phone.owner', '081799998888');
        $payload = $this->registrationPayload('phone.duplicate', '+6281799998888', '0001234567890124', null);
        $this->postJson('/api/v1/admin/partnership/registrations', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('mobile_phone');
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", [
            'mobile_phone' => '6281799998888',
        ])->assertUnprocessable()->assertJsonValidationErrors('mobile_phone');
        $this->assertDatabaseCount('member_registration', 1);
        $this->assertSame('+6281234567890', $registration->fresh()->member_registration_mobilephone);
        Http::assertNothingSent();
    }

    public function test_approval_rechecks_equivalent_phones_before_creating_member_or_calling_internal(): void
    {
        $registration = $this->pendingDistributorRegistration();
        $this->createMemberAccount('DST', '0001/0000/0000', 'phone.owner', '081234567890');
        $this->postJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}/approve")
            ->assertUnprocessable()->assertJsonPath('message', 'Nomor WhatsApp calon mitra sudah digunakan.');
        $this->assertPendingApprovalHasNoMember($registration, 1);
        Http::assertNothingSent();
    }

    public function test_registration_can_keep_its_own_pending_phone_but_not_another_pending_phone(): void
    {
        $registration = $this->pendingDistributorRegistration();
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $this->patchJson($url, ['mobile_phone' => '6281234567890'])->assertOk();
        $registration->replicate()->fill(['member_registration_mobilephone' => '081799998888'])->save();
        $this->patchJson($url, ['mobile_phone' => '+6281799998888'])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile_phone');
    }

    public function test_admin_can_edit_requested_registration_without_approving_or_syncing_it(): void
    {
        $registration = $this->pendingDistributorRegistration();
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_edit', true);
        $this->getJson('/api/v1/admin/partnership/registrations')->assertOk()
            ->assertJsonPath('data.results.0.can_edit', true);
        $payload = $this->registrationPayload('corrected', '081234567899', '0001234567890999', null);
        $payload['name'] = 'Pemohon Dikoreksi';
        $payload['address'] = 'Jalan Koreksi';
        $payload['bank_accounts'] = [[
            'bank_id' => 1, 'account_name' => 'Nama Rekening Baru', 'account_number' => '00123456',
            'city' => 'Surabaya', 'branch' => 'Cabang Baru', 'is_active' => true, 'is_default' => true,
        ]];
        $payload['nib'] = '123456';
        $payload['npwp'] = '0012345678901234';

        $this->patchJson($url, $payload)->assertOk()
            ->assertJsonPath('data.applicant.name', 'Pemohon Dikoreksi')
            ->assertJsonPath('data.applicant.mobile_phone', '+6281234567899')
            ->assertJsonPath('data.identity.number', '0001234567890999')
            ->assertJsonPath('data.address.address', 'Jalan Koreksi')
            ->assertJsonPath('data.bank.account_number', '00123456')
            ->assertJsonPath('data.bank_accounts.0.branch', 'Cabang Baru')
            ->assertJsonPath('data.status.code', 'requested')
            ->assertJsonPath('data.can_edit', true);
        $this->assertSame('DST', $registration->fresh()->level->member_level_code);
        $this->assertSame(0, (int) $registration->fresh()->member_registration_upline_member_id);
        $this->assertDatabaseCount('member_registration', 1);
        $this->assertDatabaseCount('member', 0);
        $this->assertDatabaseCount('member_account', 0);
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    #[DataProvider('emptyRegistrationMedia')]
    public function test_registration_edit_preserves_unreplaced_photos_and_unrelated_data(array $media): void
    {
        $registration = $this->pendingDistributorRegistration();
        $registration->update([
            'member_registration_image' => 'https://cdn.example.test/profile.jpg',
            'member_registration_identity_image' => 'https://cdn.example.test/identity.jpg',
            'member_registration_payment_proof_image' => 'https://cdn.example.test/payment.jpg',
        ]);
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", ['name' => 'Nama Baru', ...$media])
            ->assertOk()->assertJsonPath('data.applicant.name', 'Nama Baru');
        $registration->refresh();
        $this->assertSame('https://cdn.example.test/profile.jpg', $registration->member_registration_image);
        $this->assertSame('https://cdn.example.test/identity.jpg', $registration->member_registration_identity_image);
        $this->assertSame('https://cdn.example.test/payment.jpg', $registration->member_registration_payment_proof_image);
        $this->assertSame('0001234567890123', $registration->member_registration_identity_no);
        $this->assertSame('Jalan DNY Nomor 1', $registration->member_registration_address);
        $this->assertSame('1234567890', $registration->member_registration_bank_account_no);
    }

    public static function emptyRegistrationMedia(): array
    {
        return ['omitted' => [[]], 'null' => [['image_url' => null, 'identity_image_url' => null, 'payment_proof_image_url' => null]],
            'empty' => [['image_url' => '', 'identity_image_url' => '', 'payment_proof_image_url' => '']]];
    }

    public function test_admin_registration_edit_can_replace_documents_without_persisting_signatures(): void
    {
        $registration = $this->pendingDistributorRegistration();
        $path = '/api/v1/admin/media/uploads/12345678-1234-1234-1234-123456789012/content.jpg';
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", [
            'image_url' => 'https://cdn.example.test/new-profile.jpg',
            'identity_image_url' => url($path).'?signature=temporary',
            'payment_proof_image_url' => url($path).'?signature=temporary',
        ])->assertOk();
        $registration->refresh();
        $this->assertSame('https://cdn.example.test/new-profile.jpg', $registration->member_registration_image);
        $this->assertStringEndsWith($path, $registration->member_registration_identity_image);
        $this->assertStringEndsWith($path, $registration->member_registration_payment_proof_image);
        $this->assertStringNotContainsString('signature=', $registration->member_registration_identity_image);
        $this->assertStringNotContainsString('signature=', $registration->member_registration_payment_proof_image);
    }

    #[DataProvider('immutableRegistrationFields')]
    public function test_admin_registration_edit_cannot_change_approval_or_network_fields(string $field, mixed $value): void
    {
        $registration = $this->pendingDistributorRegistration();
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", ['name' => 'Must Not Save', $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('Calon Mitra', $registration->fresh()->member_registration_name);
        $this->assertSame('requested', $registration->member_registration_status);
        Http::assertNothingSent();
    }

    public static function immutableRegistrationFields(): array
    {
        return ['sponsor' => ['sponsor_id', 9],
            'status' => ['status', 'approved'], 'member' => ['member_id', 9], 'customer' => ['customer_number', '999'],
            'code' => ['code', '0001/0000/0000'], 'username' => ['username', 'other'], 'password' => ['password', 'Secret123'],
            'distributor approval' => ['distributor_approval_status', 'approved']];
    }

    #[DataProvider('nonEditableRegistrationStates')]
    public function test_admin_registration_edit_requires_requested_and_ready_for_admin(string $status, string $distributorStatus): void
    {
        $registration = $this->pendingDistributorRegistration();
        $registration->update(['member_registration_status' => $status, 'member_registration_distributor_approval_status' => $distributorStatus]);
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", ['name' => 'Must Not Save'])
            ->assertUnprocessable();
        $this->assertSame('Calon Mitra', $registration->fresh()->member_registration_name);
        Http::assertNothingSent();
    }

    public static function nonEditableRegistrationStates(): array
    {
        return ['approved' => ['approved', 'not_required'], 'rejected' => ['rejected', 'not_required'],
            'awaiting distributor' => ['requested', 'pending']];
    }

    #[DataProvider('registrationEditCollisions')]
    public function test_admin_registration_edit_rejects_another_member_or_registration_identity(string $owner): void
    {
        $registration = $this->pendingDistributorRegistration();
        if ($owner === 'member') {
            [$member] = $this->createMemberAccount('DST', '0001/0000/0000', 'existing');
            $member->update(['member_identity_no' => '0001234567890999']);
        } else {
            MemberRegistration::query()->create([
                ...$registration->getAttributes(), 'member_registration_id' => 2,
                'member_registration_identity_no' => '0001234567890999', 'member_registration_status' => $owner,
                'member_registration_mobilephone' => '+6281234567899',
            ]);
        }
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", ['identity_no' => '0001234567890999'])
            ->assertUnprocessable()->assertJsonValidationErrors('identity_no');
        $this->assertSame('0001234567890123', $registration->fresh()->member_registration_identity_no);
    }

    public static function registrationEditCollisions(): array
    {
        return ['member' => ['member'], 'requested application' => ['requested'], 'approved application' => ['approved']];
    }

    public function test_registration_edit_accepts_own_identity_and_unchanged_legacy_document(): void
    {
        $registration = $this->pendingDistributorRegistration();
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $this->patchJson($url, ['identity_type' => 'KTP', 'identity_no' => '0001234567890123'])->assertOk();
        $registration->update(['member_registration_identity_type' => 'SIM', 'member_registration_identity_no' => '001234567890']);
        $this->patchJson($url, ['identity_type' => 'SIM', 'identity_no' => '001234567890'])->assertOk();
        $this->patchJson($url, ['identity_no' => '123'])->assertUnprocessable()->assertJsonValidationErrors('identity_no');
    }

    public function test_registration_edit_uses_the_same_lock_as_approve_and_reject(): void
    {
        $registration = $this->pendingDistributorRegistration();
        $lock = Cache::lock('partnership:registration:'.$registration->getKey(), 60);
        $this->assertTrue($lock->get());
        try {
            $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
            $this->patchJson($url, ['name' => 'Busy'])->assertStatus(409);
            $this->postJson($url.'/approve')->assertStatus(409);
            $this->postJson($url.'/reject', ['note' => 'Busy'])->assertStatus(409);
            $this->assertSame('Calon Mitra', $registration->fresh()->member_registration_name);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_admin_approval_uses_corrected_registration_fields_for_internal_customer_creation(): void
    {
        $registration = $this->pendingDistributorRegistration();
        Http::fake([
            'registration-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'registration-supply.test/*/tambah-customer' => Http::response(['success' => true, 'data' => ['cust_no' => '10030900']]),
        ]);
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $this->patchJson($url, ['name' => 'Pemohon Baru', 'identity_no' => '0001234567890999', 'mobile_phone' => '081234567899'])
            ->assertOk();
        Http::assertNothingSent();
        $this->postJson($url.'/approve')->assertOk()->assertJsonPath('data.can_edit', false);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/tambah-customer')
            && $request['NoKTP'] === '0001234567890999');
        $member = Member::query()->sole();
        $this->assertSame('Pemohon Baru', $member->member_name);
        $this->assertSame('10030900', $member->member_supply_chain_code);
    }

    public function test_registration_edit_requires_admin_authentication(): void
    {
        $this->patchJson('/api/v1/admin/partnership/registrations/1', ['name' => 'Unauthorized'])->assertUnauthorized();
    }

    public function test_registration_edit_can_correct_level_and_upline_before_approval(): void
    {
        $registration = $this->pendingDistributorRegistration();
        [$distributor] = $this->createMemberAccount('DST', '0080/0000/0000', 'sponsor');
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $options = $this->getJson('/api/v1/admin/partnership/registrations/options')->assertOk();
        $options->assertJsonCount(1, 'data.levels')->assertJsonCount(3, 'data.edit_levels')
            ->assertJsonPath('data.edit_uplines.0.id', $distributor->getKey())
            ->assertJsonPath('data.edit_uplines.0.level_name', 'Distributor')
            ->assertJsonPath('data.edit_uplines.0.label', $distributor->member_code.' - '.$distributor->member_name.' (Distributor)');
        $this->patchJson($url, ['level_id' => $this->level('AGT')->getKey(), 'upline_member_id' => $distributor->getKey()])
            ->assertOk()->assertJsonPath('data.level.code', 'AGT')->assertJsonPath('data.sponsor.id', $distributor->getKey())
            ->assertJsonPath('data.status.code', 'requested');
        Http::assertNothingSent();
        Http::fake(['registration-supply.test/*' => fn (Request $request) => Http::response([
            'success' => true, 'data' => str_ends_with($request->url(), '/login') ? ['api_token' => 'test-token'] : ['cust_no' => 'TEST-CUSTOMER'],
        ])]);
        $this->postJson($url.'/approve')->assertOk();
        $created = Member::query()->whereKey($registration->fresh()->member_registration_member_id)->firstOrFail();
        $this->assertSame('0080/0001/0000', $created->member_code);
        $this->assertSame((int) $distributor->getKey(), (int) $created->member_parent_member_id);
        $this->patchJson($url, ['level_id' => $this->level('DST')->getKey(), 'upline_member_id' => null])
            ->assertUnprocessable();
        $this->assertSame('AGT', $created->fresh()->level->member_level_code);
    }

    public function test_registration_edit_rejects_invalid_or_inactive_upline_and_requires_consistent_pair(): void
    {
        $registration = $this->pendingDistributorRegistration();
        [$reseller] = $this->createMemberAccount('RSL', '0080/0000/0001', 'reseller');
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        foreach ([['level_id' => $this->level('AGT')->getKey(), 'upline_member_id' => null],
            ['level_id' => $this->level('AGT')->getKey(), 'upline_member_id' => $reseller->getKey()],
            ['level_id' => $this->level('DST')->getKey(), 'upline_member_id' => $reseller->getKey()],
            ['level_id' => 999, 'upline_member_id' => null]] as $payload) {
            $this->patchJson($url, $payload)->assertUnprocessable();
        }
        $reseller->update(['member_status' => 0]);
        $this->patchJson($url, ['level_id' => $this->level('RSL')->getKey(), 'upline_member_id' => $reseller->getKey()])->assertUnprocessable();
        $this->assertSame('DST', $registration->fresh()->level->member_level_code);
        $this->assertSame(0, (int) $registration->member_registration_upline_member_id);
        Http::assertNothingSent();
    }

    public function test_registration_edit_invalidates_old_distributor_review_when_agent_upline_changes(): void
    {
        $registration = $this->pendingDistributorRegistration();
        [$oldDistributor] = $this->createMemberAccount('DST', '0080/0000/0000', 'old.dist');
        [$newDistributor] = $this->createMemberAccount('DST', '0081/0000/0000', 'new.dist');
        [$agent] = $this->createMemberAccount('AGT', '0081/0001/0000', 'new.agent');
        $agent->update(['member_parent_member_id' => $newDistributor->getKey()]);
        $registration->update(['member_registration_member_level_id' => $this->level('RSL')->getKey(),
            'member_registration_distributor_approval_status' => 'approved',
            'member_registration_distributor_member_id' => $oldDistributor->getKey(),
            'member_registration_distributor_approval_note' => 'Old review', 'member_registration_distributor_approval_datetime' => now()]);
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $this->patchJson($url, ['upline_member_id' => $agent->getKey()])->assertOk()
            ->assertJsonPath('data.status.code', 'requested')->assertJsonPath('data.next_approval', 'distributor')
            ->assertJsonPath('data.distributor_approval.distributor.id', $newDistributor->getKey())
            ->assertJsonPath('data.distributor_approval.note', null)->assertJsonPath('data.can_edit', false);
        $this->postJson($url.'/approve')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_registration_edit_rejects_duplicate_phone_after_normalization(): void
    {
        $registration = $this->pendingDistributorRegistration();
        [$member] = $this->createMemberAccount('DST', '0001/0000/0000', 'existing');
        $member->update(['member_mobilephone' => '+6281234567899']);
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", [
            'mobile_phone' => '081234567899', 'name' => 'Must Not Save',
        ])->assertUnprocessable()->assertJsonValidationErrors('mobile_phone');
        $this->assertSame('Calon Mitra', $registration->fresh()->member_registration_name);
        Http::assertNothingSent();
    }

    public function test_registration_edit_validates_partial_region_against_the_saved_address(): void
    {
        $registration = $this->pendingDistributorRegistration();
        DB::table('ref_province')->insert(['province_id' => '12', 'province_name' => 'Provinsi Lain']);
        $this->patchJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}", ['province_id' => '12'])
            ->assertUnprocessable()->assertJsonValidationErrors('province_id');
        $this->assertSame('11', (string) $registration->fresh()->member_registration_province_id);
    }

    public function test_registration_edit_keeps_bank_fields_when_omitted_and_can_explicitly_clear_them(): void
    {
        $registration = $this->pendingDistributorRegistration();
        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}";
        $banks = [[
            'bank_id' => 1, 'account_name' => 'Nama Utama', 'account_number' => '001234',
            'is_active' => true, 'is_default' => true,
        ], [
            'bank_id' => 1, 'account_name' => 'Nama Kedua', 'account_number' => '005678',
            'is_active' => true, 'is_default' => false,
        ]];
        $this->patchJson($url, ['bank_accounts' => $banks])->assertOk()->assertJsonCount(2, 'data.bank_accounts');
        $this->patchJson($url, ['name' => 'Nama Baru'])->assertOk()
            ->assertJsonCount(2, 'data.bank_accounts')->assertJsonPath('data.bank.account_number', '001234');
        $this->patchJson($url, ['bank_accounts' => [$banks[0], [...$banks[1], 'is_default' => true]]])
            ->assertUnprocessable()->assertJsonValidationErrors('bank_accounts');
        $this->patchJson($url, ['bank_accounts' => null])->assertUnprocessable()->assertJsonValidationErrors('bank_accounts');
        $this->patchJson($url, ['bank_accounts' => []])->assertOk()->assertJsonCount(0, 'data.bank_accounts')
            ->assertJsonPath('data.bank', null);
        $this->assertSame(0, $registration->fresh()->member_registration_bank_id);
        Http::assertNothingSent();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.supply_chain.enabled', true);
        config()->set('services.supply_chain.base_url', 'https://registration-default.test');
        config()->set('services.supply_chain.username', 'registration-test');
        config()->set('services.supply_chain.password', 'test-secret');
        Http::preventStrayRequests();
        Http::fake(['registration-default.test/*' => fn (Request $request) => Http::response([
            'success' => true,
            'data' => str_ends_with($request->url(), '/login')
                ? ['api_token' => 'registration-token']
                : ['cust_no' => 'CUST-'.($request['NoKTP'] ?? 'DEFAULT')],
        ])]);
    }

    #[DataProvider('supplyChainApprovalFailures')]
    public function test_approval_waits_for_a_customer_number_and_preserves_the_supply_chain_message(
        array $body,
        int $status,
        string $message,
    ): void {
        $registration = $this->pendingDistributorRegistration();
        Queue::fake();
        Http::fake([
            'registration-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'registration-supply.test/*/tambah-customer' => Http::response($body, $status),
        ]);

        $this->postJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}/approve")
            ->assertStatus(502)
            ->assertJsonPath('message', 'Error pada Aplikasi Internal: '.$message);

        $this->assertPendingApprovalHasNoMember($registration);
        Queue::assertNotPushed(SyncSupplyChainMember::class);
        Mail::assertNothingOutgoing();
    }

    public static function supplyChainApprovalFailures(): array
    {
        return [
            'business rejection' => [
                ['success' => false, 'message' => 'Nomor KTP sudah terdaftar di customer lain.'],
                200, 'Nomor KTP sudah terdaftar di customer lain.',
            ],
            'http rejection' => [
                ['success' => false, 'message' => 'Data customer tidak lengkap: NoKTP.'],
                422, 'Data customer tidak lengkap: NoKTP.',
            ],
            'duplicate phone' => [
                ['success' => false, 'message' => "Customer dengan nomor telepon '+628179254440' sudah terdaftar pada sistem (data kembar)."],
                200, "Customer dengan nomor telepon '+628179254440' sudah terdaftar pada sistem (data kembar).",
            ],
            'duplicate customer with a number and HTTP 200' => [
                [
                    'success' => false,
                    'message' => "Customer dengan No KTP '3171012345670001' sudah terdaftar pada sistem (data kembar).",
                    'data' => ['nama' => 'Budi Santoso', 'Cust_no' => '10030900 ', 'no_kemitraan' => 'KM-2026-002'],
                ],
                200, "Customer dengan No KTP '3171012345670001' sudah terdaftar pada sistem (data kembar).",
            ],
            'duplicate customer with a number and HTTP 400' => [
                [
                    'success' => false,
                    'message' => "Customer dengan No KTP '3171012345670001' sudah terdaftar pada sistem (data kembar).",
                    'data' => ['nama' => 'Budi Santoso', 'Cust_no' => '10030900 ', 'no_kemitraan' => 'KM-2026-002'],
                ],
                400, "Customer dengan No KTP '3171012345670001' sudah terdaftar pada sistem (data kembar).",
            ],
            'access rejected' => [
                ['success' => false, 'message' => 'Akun tidak memiliki akses tambah customer.'],
                403, 'Akun tidak memiliki akses tambah customer.',
            ],
            'missing number' => [
                ['success' => true, 'data' => []],
                200, 'Nomor customer tidak ditemukan pada respons Aplikasi Internal.',
            ],
            'empty number with provider message' => [
                ['success' => true, 'message' => 'Customer belum mendapatkan nomor.', 'data' => ['cust_no' => '']],
                200, 'Customer belum mendapatkan nomor.',
            ],
            'whitespace number' => [
                ['success' => true, 'data' => ['cust_no' => '   ']],
                200, 'Nomor customer tidak ditemukan pada respons Aplikasi Internal.',
            ],
            'malformed number' => [
                ['success' => true, 'data' => ['cust_no' => ['90000046']]],
                200, 'Nomor customer tidak ditemukan pada respons Aplikasi Internal.',
            ],
        ];
    }

    public function test_approval_is_held_when_supply_chain_is_disabled(): void
    {
        $registration = $this->pendingDistributorRegistration();
        config()->set('services.supply_chain.enabled', false);

        $this->postJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}/approve")
            ->assertStatus(503)
            ->assertJsonPath('message', 'Layanan Aplikasi Internal belum diaktifkan.');

        $this->assertPendingApprovalHasNoMember($registration);
        Http::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_approval_is_held_when_supply_chain_cannot_be_reached(): void
    {
        $registration = $this->pendingDistributorRegistration();
        Http::fake(['registration-supply.test/*' => Http::failedConnection()]);

        $this->postJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}/approve")
            ->assertStatus(503)
            ->assertJsonPath('message', 'Layanan Aplikasi Internal sedang tidak dapat dihubungi.');

        $this->assertPendingApprovalHasNoMember($registration);
        Mail::assertNothingOutgoing();
    }

    public function test_approval_preserves_the_login_rejection_message(): void
    {
        $registration = $this->pendingDistributorRegistration();
        Http::fake(['registration-supply.test/*/login' => Http::response([
            'success' => false, 'message' => 'Username atau password salah.', 'data' => [],
        ])]);

        $this->postJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}/approve")
            ->assertStatus(502)
            ->assertJsonPath('message', 'Error pada Aplikasi Internal: Username atau password salah.');

        $this->assertPendingApprovalHasNoMember($registration);
        Mail::assertNothingOutgoing();
    }

    public function test_approval_rejects_a_customer_number_owned_by_another_member(): void
    {
        $registration = $this->pendingDistributorRegistration();
        [$owner] = $this->createMemberAccount('DST', '0001/0000/0000', 'customer.owner', '+6281111111111');
        $owner->update(['member_supply_chain_code' => '90000046']);
        Http::fake([
            'registration-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'registration-supply.test/*/tambah-customer' => Http::response(['success' => true, 'data' => ['cust_no' => '90000046']]),
        ]);

        $this->postJson("/api/v1/admin/partnership/registrations/{$registration->getKey()}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', "Nomor customer '90000046' dari Aplikasi Internal sudah digunakan oleh mitra lain.");

        $this->assertPendingApprovalHasNoMember($registration, 1);
        $this->assertSame('90000046', $owner->fresh()->member_supply_chain_code);
        Mail::assertNothingOutgoing();
    }

    public function test_approval_retry_uses_the_existing_customer_and_commits_only_once(): void
    {
        $registration = $this->pendingDistributorRegistration();
        Queue::fake();
        Http::fake([
            'registration-supply.test/*/login' => Http::response(['success' => true, 'data' => ['api_token' => 'test-token']]),
            'registration-supply.test/*/tambah-customer' => Http::sequence()
                ->push(['success' => false, 'message' => 'Customer belum siap.'], 422)
                ->push(['success' => false, 'message' => 'Customer sudah terdaftar (data kembar).', 'data' => ['Cust_no' => ' 90000046 ']], 400)
                ->push(['success' => true, 'message' => 'Customer berhasil diproses.', 'data' => ['Cust_no' => ' 90000046 ']]),
        ]);

        $url = "/api/v1/admin/partnership/registrations/{$registration->getKey()}/approve";
        $this->postJson($url)->assertStatus(502)->assertJsonPath('message', 'Error pada Aplikasi Internal: Customer belum siap.');
        $this->assertPendingApprovalHasNoMember($registration);
        Mail::assertNothingOutgoing();

        $this->postJson($url)->assertStatus(502)->assertJsonPath('message', 'Error pada Aplikasi Internal: Customer sudah terdaftar (data kembar).');
        $this->assertPendingApprovalHasNoMember($registration);
        Mail::assertNothingOutgoing();

        $this->postJson($url)->assertOk()->assertJsonPath('data.status.code', 'approved');
        $member = Member::query()->findOrFail($registration->fresh()->member_registration_member_id);
        $this->assertSame('90000046', $member->member_supply_chain_code);
        $this->assertDatabaseCount('member', 1);
        $this->assertDatabaseCount('member_account', 1);
        $this->assertDatabaseCount('member_history', 1);
        $this->assertDatabaseHas('member_supply_chain_syncs', [
            'member_supply_chain_sync_member_id' => $member->getKey(),
            'member_supply_chain_sync_customer_no' => '90000046',
            'member_supply_chain_sync_status' => 'synced',
        ]);
        Queue::assertNotPushed(SyncSupplyChainMember::class);
        Mail::assertSent(MemberRegistrationApprovedMail::class, 1);

        $this->postJson($url)->assertUnprocessable();
        Http::assertSentCount(4);
        $this->assertDatabaseCount('member_account', 1);
        Mail::assertSent(MemberRegistrationApprovedMail::class, 1);
    }

    #[DataProvider('identityRegistrationSources')]
    public function test_registration_rejects_an_identity_used_by_an_existing_member(bool $admin): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [$member, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'identity.owner');
        $member->update(['member_identity_no' => '0001234567890123']);
        $this->actingAs($admin ? $this->createAdministrator() : $account, $admin ? 'admin_api' : 'member_api');
        $url = $admin ? '/api/v1/admin/partnership/registrations' : '/api/v1/member/network/registrations';

        $this->postJson($url, $this->registrationPayload('identity.duplicate', '081234567890', '0001234567890123', $admin ? null : 'AGT'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identity_no')
            ->assertJsonPath('message', 'Nomor identitas sudah digunakan atau masih dalam proses pendaftaran.');

        $this->assertDatabaseCount('member_registration', 0);
    }

    #[DataProvider('identityRegistrationSources')]
    public function test_registration_rejects_an_identity_reserved_by_a_pending_application(bool $admin): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'identity.sponsor');
        $this->actingAs($admin ? $this->createAdministrator() : $account, $admin ? 'admin_api' : 'member_api');
        $url = $admin ? '/api/v1/admin/partnership/registrations' : '/api/v1/member/network/registrations';
        $levelCode = $admin ? null : 'AGT';
        $this->postJson($url, $this->registrationPayload('identity.first', '081234567891', '0001234567890123', $levelCode))
            ->assertSuccessful();

        $this->postJson($url, $this->registrationPayload('identity.second', '081234567892', '0001234567890123', $levelCode))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identity_no');

        $this->assertDatabaseCount('member_registration', 1);
    }

    public static function identityRegistrationSources(): array
    {
        return ['admin' => [true], 'member' => [false]];
    }

    private function pendingDistributorRegistration(): MemberRegistration
    {
        Mail::fake();
        config()->set('services.supply_chain.base_url', 'https://registration-supply.test');
        $this->createReferenceData();
        $this->createMemberGroups();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $id = $this->postJson('/api/v1/admin/partnership/registrations',
            $this->registrationPayload('sync.applicant', '081234567890', '0001234567890123', null)
        )->assertSuccessful()->json('data.id');

        return MemberRegistration::query()->findOrFail($id);
    }

    private function assertPendingApprovalHasNoMember(MemberRegistration $registration, int $existingMembers = 0): void
    {
        $registration->refresh();
        $this->assertSame('requested', $registration->member_registration_status);
        $this->assertSame(0, (int) $registration->member_registration_member_id);
        $this->assertDatabaseCount('member', $existingMembers);
        $this->assertDatabaseCount('member_account', $existingMembers);
        $this->assertDatabaseCount('member_history', 0);
        $this->assertDatabaseCount('member_address', 0);
        $this->assertDatabaseCount('member_bank_account', 0);
        $this->assertDatabaseCount('member_supply_chain_syncs', 0);
    }

    public function test_identity_type_rollback_preserves_members_using_new_types(): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [$member] = $this->createMemberAccount('DST', '0001/0000/0000', 'rollback.member');
        $member->update(['member_identity_type' => 'KK', 'member_identity_no' => '0001234567890123']);
        $migration = require database_path('migrations/2026_10_15_073858_extend_member_identity_document_types.php');
        $failure = null;
        try {
            $migration->down();
        } catch (\Throwable $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(\LogicException::class, $failure, 'Rollback must stop before changing a document type still in use.');
        $this->assertStringContainsString('records exist', $failure->getMessage());
        $this->assertSame('KK', $member->fresh()->member_identity_type);
        $this->assertSame('0001234567890123', $member->fresh()->member_identity_no);
    }

    public function test_identity_type_rollback_preserves_registrations_using_new_types(): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'rollback.sponsor');
        $this->actingAs($account, 'member_api');
        $id = $this->postJson('/api/v1/member/network/registrations', [
            ...$this->registrationPayload('rollback.applicant', '081234567890', '00012345678'),
            'identity_type' => 'BPJS_KETENAGAKERJAAN',
        ])->assertSuccessful()->json('data.id');
        $migration = require database_path('migrations/2026_10_15_073858_extend_member_identity_document_types.php');
        $failure = null;
        try {
            $migration->down();
        } catch (\Throwable $exception) {
            $failure = $exception;
        }
        $this->assertInstanceOf(\LogicException::class, $failure, 'Rollback must stop before changing a document type still in use.');
        $this->assertStringContainsString('records exist', $failure->getMessage());
        $registration = MemberRegistration::query()->findOrFail($id);
        $this->assertSame('BPJS_KETENAGAKERJAAN', $registration->member_registration_identity_type);
        $this->assertSame('00012345678', $registration->member_registration_identity_no);
    }

    public function test_identity_types_can_be_rolled_back_and_reapplied_without_changing_old_numbers(): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [$member] = $this->createMemberAccount('DST', '0001/0000/0000', 'rollback.legacy');
        $member->update(['member_identity_type' => 'SIM', 'member_identity_no' => '001234567890']);
        $migration = require database_path('migrations/2026_10_15_073858_extend_member_identity_document_types.php');
        $migration->down();
        $this->assertSame('SIM', $member->fresh()->member_identity_type);
        $this->assertSame('001234567890', $member->fresh()->member_identity_no);
        $migration->up();
        $member->update(['member_identity_type' => 'KK']);
        $this->assertSame('KK', $member->fresh()->member_identity_type);
        $this->assertSame('001234567890', $member->fresh()->member_identity_no);
    }

    #[DataProvider('validIdentities')]
    public function test_new_registrations_accept_document_formats_and_preserve_leading_zeroes(string $type, string $number): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'identity.sponsor');
        $this->actingAs($account, 'member_api');
        $payload = $this->registrationPayload('identity.applicant', '081234567890', $number);
        $payload['identity_type'] = strtolower($type);
        $id = $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertSuccessful()->assertJsonPath('data.identity.type', $type)
            ->assertJsonPath('data.identity.number', $number)->json('data.id');
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")->assertOk();
        $member = Member::query()->where('member_mobilephone', '+6281234567890')->firstOrFail();
        $this->assertSame($type, $member->member_identity_type);
        $this->assertSame($number, $member->member_identity_no);
    }

    #[DataProvider('validIdentities')]
    public function test_admin_distributor_creation_accepts_document_formats(string $type, string $number): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $payload = $this->registrationPayload('identity.distributor', '081234567890', $number, null);
        $payload['identity_type'] = $type;
        $id = $this->postJson('/api/v1/admin/partnership/registrations', $payload)
            ->assertSuccessful()->assertJsonPath('data.identity.type', $type)->json('data.id');
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/reject", ['note' => 'Pengujian identitas'])->assertOk();
        $this->postJson('/api/v1/admin/partnership/distributors', [
            ...$payload, 'domicile_address' => 'Jalan Domisili', 'status' => 1,
        ])->assertCreated()->assertJsonPath('data.identity.type', $type)
            ->assertJsonPath('data.identity.no', $number);
    }

    #[DataProvider('invalidIdentities')]
    public function test_new_registrations_reject_invalid_document_numbers(string $type, mixed $number): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'invalid.identity.sponsor');
        $this->actingAs($account, 'member_api');
        $payload = $this->registrationPayload('invalid.identity', '081234567890', '0001234567890123');
        $payload['identity_type'] = $type;
        $payload['identity_no'] = $number;
        $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('identity_no');
        unset($payload['level_id']);
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson('/api/v1/admin/partnership/registrations', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('identity_no');
        $this->postJson('/api/v1/admin/partnership/distributors', [
            ...$payload, 'domicile_address' => 'Jalan Domisili', 'status' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('identity_no');
        $this->assertDatabaseCount('member_registration', 0);
    }

    public static function validIdentities(): array
    {
        return [
            'ktp' => ['KTP', '0001234567890123'],
            'kk' => ['KK', '0001234567890123'],
            'sim' => ['SIM', '0001234567890123'],
            'health' => ['BPJS', '0001234567890'],
            'employment kpj' => ['BPJS_KETENAGAKERJAAN', '00012345678'],
            'employment nik' => ['BPJS_KETENAGAKERJAAN', '0001234567890123'],
            'passport' => ['PASPOR', 'A0123456'],
        ];
    }

    public static function invalidIdentities(): array
    {
        return [
            'short ktp' => ['KTP', '123456789012345'],
            'long kk' => ['KK', '12345678901234567'],
            'old sim' => ['SIM', '123456789012'],
            'health short' => ['BPJS', '123456789012'],
            'health long' => ['BPJS', '12345678901234'],
            'employment between' => ['BPJS_KETENAGAKERJAAN', '123456789012'],
            'numeric letters' => ['KTP', 'A234567890123456'],
            'numeric punctuation' => ['BPJS', '123456789012-'],
            'passport short' => ['PASPOR', 'A123456'],
            'passport long' => ['PASPOR', 'A12345678'],
            'passport punctuation' => ['PASPOR', 'A123-567'],
            'number instead of string' => ['KTP', 1234567890123456],
        ];
    }

    public function test_registration_documents_are_visible_in_history_approval_and_member_directory(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'document.sponsor');
        $this->actingAs($account, 'member_api');
        $this->getJson('/api/v1/member/network/registrations/options')
            ->assertJsonPath('data.identity_types', ['KTP', 'KK', 'SIM', 'BPJS', 'BPJS_KETENAGAKERJAAN', 'PASPOR']);
        $payload = [
            ...$this->registrationPayload('documents', '081234567810', '0001234567890'),
            'identity_type' => 'bpjs',
            'identity_image_url' => 'https://example.test/bpjs.jpg',
            'nib' => '0123456789012',
            'image_url' => 'https://example.test/profile.jpg',
            'payment_proof_image_url' => 'https://example.test/transfer.jpg',
        ];
        $id = $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.identity.type', 'BPJS')
            ->assertJsonPath('data.identity.image_url', $payload['identity_image_url'])
            ->assertJsonPath('data.payment_proof_image_url', $payload['payment_proof_image_url'])
            ->assertJsonPath('data.image_url', $payload['image_url'])
            ->assertJsonPath('data.npwp', null)->json('data.id');
        $this->getJson('/api/v1/member/network/registrations')
            ->assertJsonPath('data.results.0.identity.type', 'BPJS')
            ->assertJsonPath('data.results.0.nib', $payload['nib'])
            ->assertJsonPath('data.results.0.payment_proof_image_url', $payload['payment_proof_image_url']);
        $this->getJson("/api/v1/member/network/registrations/{$id}")
            ->assertJsonPath('data.npwp', null);
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->getJson('/api/v1/admin/partnership/registrations')
            ->assertJsonPath('data.results.0.npwp', null);
        $this->getJson("/api/v1/admin/partnership/registrations/{$id}")
            ->assertJsonPath('data.payment_proof_image_url', $payload['payment_proof_image_url']);
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")
            ->assertOk()->assertJsonPath('data.status.code', 'approved');
        $member = Member::query()->where('member_mobilephone', '+6281234567810')->firstOrFail();
        $this->assertDatabaseHas('member', [
            'member_id' => $member->getKey(),
            'member_identity_type' => 'BPJS',
            'member_nib' => $payload['nib'],
            'member_npwp' => null,
            'member_image' => $payload['image_url'],
        ]);
        $this->getJson("/api/v1/admin/partnership/members/{$member->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.identity.type', 'BPJS')
            ->assertJsonPath('data.npwp', null)
            ->assertJsonPath('data.image', $payload['image_url'])
            ->assertJsonPath('data.payment_proof_image_url', $payload['payment_proof_image_url']);
        $this->getJson('/api/v1/admin/partnership/members?search=documents')
            ->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.npwp', null)
            ->assertJsonPath('data.results.0.payment_proof_image_url', $payload['payment_proof_image_url']);
        $this->actingAs($member->accounts()->firstOrFail(), 'member_api');
        $this->getJson('/api/v1/member/profile')
            ->assertOk()->assertJsonPath('data.member.nib', $payload['nib'])
            ->assertJsonPath('data.member.npwp', null)
            ->assertJsonPath('data.member.image', $payload['image_url'])
            ->assertJsonPath('data.member.identity.image', $payload['identity_image_url'])
            ->assertJsonPath('data.member.payment_proof_image_url', $payload['payment_proof_image_url'])
            ->assertJsonCount(1, 'data.member.addresses')->assertJsonCount(1, 'data.member.bank_accounts');
    }

    public function test_member_registration_does_not_accept_npwp(): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'npwp.sponsor');
        $this->actingAs($account, 'member_api');
        $this->postJson('/api/v1/member/network/registrations', [
            ...$this->registrationPayload('without.npwp', '081234567831', '0001234567831'),
            'npwp' => '0123456789012345',
        ])->assertUnprocessable()->assertJsonValidationErrors('npwp')
            ->assertJsonPath('errors.npwp.0', 'NPWP tidak digunakan pada pendaftaran member.');
        $this->assertDatabaseCount('member_registration', 0);
    }

    public function test_distributor_registration_snapshots_multiple_banks_and_approval_copies_all(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $payload = $this->multipleContactPayload();
        $payload['nib'] = '0123456789012';
        $payload['npwp'] = '0123456789012345';
        $id = $this->postJson('/api/v1/admin/partnership/registrations', $payload)
            ->assertSuccessful()
            ->assertJsonMissingPath('data.addresses')
            ->assertJsonCount(2, 'data.bank_accounts')
            ->assertJsonPath('data.address.address', 'Jalan DNY Nomor 1')
            ->assertJsonPath('data.bank.account_number', '0012345678')
            ->json('data.id');
        $registration = MemberRegistration::query()->findOrFail($id);
        $this->assertCount(2, $registration->member_registration_bank_accounts);
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")->assertOk();
        $memberId = $registration->fresh()->member_registration_member_id;
        $this->getJson("/api/v1/admin/partnership/members/{$memberId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonPath('data.addresses.0.full_address', 'Jalan DNY Nomor 1')
            ->assertJsonPath('data.nib', $payload['nib'])->assertJsonPath('data.npwp', $payload['npwp'])
            ->assertJsonCount(2, 'data.bank_accounts')
            ->assertJsonPath('data.bank_accounts.0.account_number', '0012345678');
        $this->assertDatabaseHas('member_bank_account', [
            'member_bank_account_member_id' => $memberId,
            'member_bank_account_number' => '0012345678',
            'member_bank_account_is_default' => 1,
        ]);
    }

    public function test_distributor_registration_can_be_approved_without_bank_accounts(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        foreach (['omitted', 'empty', 'null'] as $index => $mode) {
            $payload = $this->multipleContactPayload();
            $payload['mobile_phone'] = '08123456782'.$index;
            $payload['identity_no'] = '000123456789012'.$index;
            if ($mode === 'omitted') {
                unset($payload['bank_accounts']);
            } else {
                $payload['bank_accounts'] = $mode === 'empty' ? [] : null;
            }
            $id = $this->postJson('/api/v1/admin/partnership/registrations', $payload)
                ->assertOk()->assertJsonPath('data.bank_accounts', [])
                ->assertJsonPath('data.bank', null)->json('data.id');
            $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")->assertOk();
            $memberId = MemberRegistration::query()->findOrFail($id)->member_registration_member_id;
            $this->assertDatabaseMissing('member_bank_account', ['member_bank_account_member_id' => $memberId]);
        }
    }

    public function test_private_registration_documents_can_be_opened_by_admin_and_store_canonical_urls(): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $account] = $this->createMemberAccount('DST', '0001/0000/0000', 'media.sponsor');
        Storage::fake('local');
        $uuid = (string) Str::uuid();
        $path = "media/general/{$uuid}.jpg";
        Storage::disk('local')->put($path, 'identity-document');
        $media = Media::query()->create([
            'media_uuid' => $uuid, 'media_uploader_type' => $account->getMorphClass(),
            'media_uploader_id' => $account->getKey(), 'media_collection' => 'general',
            'media_original_name' => 'identity.jpg', 'media_original_mime_type' => 'image/jpeg',
            'media_original_size' => 17, 'media_mime_type' => 'image/jpeg', 'media_size' => 17,
            'media_chunk_size' => 1024, 'media_total_chunks' => 1, 'media_uploaded_chunks' => 1,
            'media_status' => Media::STATUS_READY, 'media_disk' => 'local', 'media_path' => $path,
            'media_expires_at' => now()->addDay(),
        ]);
        $this->actingAs($account, 'member_api');
        $signedUrl = MediaUrl::privateContentUrl($media, 'member');
        $id = $this->postJson('/api/v1/member/network/registrations', [
            ...$this->registrationPayload('private.docs', '081234567813', '3578010101010013'),
            'identity_image_url' => $signedUrl,
            'payment_proof_image_url' => $signedUrl,
        ])->assertSuccessful()->json('data.id');
        $registration = MemberRegistration::query()->findOrFail($id);
        $this->assertStringNotContainsString('signature=', $registration->member_registration_identity_image);
        $this->assertStringNotContainsString('signature=', $registration->member_registration_payment_proof_image);
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $url = $this->getJson("/api/v1/admin/partnership/registrations/{$id}")->assertOk()
            ->json('data.payment_proof_image_url');
        $this->assertStringContainsString('/api/v1/admin/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->get($url)->assertOk();
        $this->getJson("/api/v1/admin/media/uploads/{$uuid}")->assertOk();
    }

    public function test_registration_rejects_unknown_identity_and_contact_ids_from_existing_members(): void
    {
        $this->createReferenceData();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $payload = $this->multipleContactPayload();
        $payload['identity_type'] = 'LAINNYA';
        $payload['addresses'] = [['full_address' => 'Alamat tambahan']];
        $payload['bank_accounts'][0]['id'] = 1;
        $this->postJson('/api/v1/admin/partnership/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identity_type', 'addresses', 'bank_accounts.0.id']);
        $this->assertDatabaseCount('member_registration', 0);
    }

    public function test_registration_rejects_multiple_defaults_invalid_regions_and_inactive_default_bank(): void
    {
        $this->createReferenceData();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $payload = $this->multipleContactPayload();
        $payload['bank_accounts'][1]['is_default'] = true;
        $payload['bank_accounts'][0]['is_active'] = false;
        DB::table('ref_province')->insert(['province_id' => '12', 'province_name' => 'Provinsi lain']);
        $payload['province_id'] = '12';
        $this->postJson('/api/v1/admin/partnership/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_accounts', 'province_id', 'bank_accounts.0.is_default']);
        $this->assertDatabaseCount('member_registration', 0);
    }

    public function test_approval_keeps_legacy_registration_contacts_without_snapshots(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $id = $this->postJson('/api/v1/admin/partnership/registrations',
            $this->registrationPayload('legacy', '081234567812', '3578010101010012', null))
            ->assertSuccessful()->json('data.id');
        MemberRegistration::query()->findOrFail($id)->update([
            'member_registration_bank_accounts' => null,
        ]);
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")->assertOk();
        $this->assertDatabaseHas('member_bank_account', ['member_bank_account_number' => '1234567890']);
        $this->assertDatabaseHas('member_address', ['member_address_full' => 'Jalan DNY Nomor 1']);
    }

    /** @return array<string, mixed> */
    private function multipleContactPayload(): array
    {
        $payload = $this->registrationPayload('contacts', '081234567811', '3578010101010011', null);
        foreach (['bank_id', 'bank_account_name', 'bank_account_number', 'bank_city', 'bank_branch'] as $field) {
            unset($payload[$field]);
        }
        $bank = ['bank_id' => 1, 'account_name' => 'Calon Mitra', 'account_number' => '0012345678',
            'is_default' => true];
        $payload['bank_accounts'] = [$bank, [...$bank, 'account_number' => '0098765432', 'is_default' => false]];

        return $payload;
    }

    public function test_member_credentials_mail_renders_registration_template(): void
    {
        $html = (new MemberCredentialsMail(
            'Mitra Uji',
            '9999/0000/0000',
            'Sementara123',
            'https://example.test/member/login'
        ))->render();

        $this->assertStringContainsString('Registrasi Kemitraan Berhasil', $html);
        $this->assertStringContainsString('Mitra Uji', $html);
        $this->assertStringContainsString('9999/0000/0000', $html);
        $this->assertStringContainsString('Sementara123', $html);
        $this->assertStringContainsString('https://example.test/member/login', $html);
    }

    public function test_member_codes_auto_increment_by_network_branch(): void
    {
        $this->createReferenceData();
        [$distributor] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.root',
        );
        $service = app(MemberCodeService::class);
        $agentLevel = $this->level('AGT');
        $resellerLevel = $this->level('RSL');

        $this->assertSame('0002/0000/0000', $service->next($this->level('DST')));
        $this->assertSame('0001/0001/0000', $service->next($agentLevel, $distributor));
        $this->assertSame('0000/0001/0000', $service->next($agentLevel));

        $agent = $this->createNetworkMember('0001/0001/0000', $agentLevel, $distributor);
        $this->assertSame('0001/0002/0000', $service->next($agentLevel, $distributor));
        $this->assertSame('0001/0001/0001', $service->next($resellerLevel, $agent));

        $this->createNetworkMember('0001/0001/0001', $resellerLevel, $agent);
        $this->assertSame('0001/0001/0002', $service->next($resellerLevel, $agent));
    }

    public function test_reseller_code_supports_distributor_or_agent_sponsor(): void
    {
        $this->createReferenceData();
        [$distributor] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.direct-reseller-sponsor',
        );
        $resellerLevel = $this->level('RSL');
        $service = app(MemberCodeService::class);

        $this->assertSame('0001/0000/0001', $service->next($resellerLevel, $distributor));

        $this->createNetworkMember('0001/0000/0001', $resellerLevel, $distributor);

        $this->assertSame('0001/0000/0002', $service->next($resellerLevel, $distributor));
    }

    #[DataProvider('reservedMemberCodeUsernames')]
    public function test_member_codes_skip_reserved_usernames_without_changing_existing_accounts(
        string $targetLevelCode,
        ?string $sponsorCode,
        array $reservedUsernames,
        string $expectedCode,
    ): void {
        $distributor = $this->createNetworkMember('0080/0000/0000', $this->level('DST'), null);
        $sponsor = match ($sponsorCode) {
            null => null,
            '0080/0000/0000' => $distributor,
            default => $this->createNetworkMember($sponsorCode, $this->level('AGT'), $distributor),
        };
        foreach ($reservedUsernames as $index => $username) {
            MemberAccount::query()->create([
                'member_account_member_id' => 9000 + $index,
                'member_account_member_group_id' => $this->level($targetLevelCode)->getKey(),
                'member_account_username' => $username,
                'member_account_password' => Hash::make('Legacy123'),
                'member_account_pin' => '123456',
            ]);
        }
        $originalAccounts = MemberAccount::query()->get()->toArray();
        $originalMemberCount = Member::query()->count();

        $this->assertSame($expectedCode, app(MemberCodeService::class)->next($this->level($targetLevelCode), $sponsor));

        $this->assertSame($originalAccounts, MemberAccount::query()->get()->toArray());
        $this->assertDatabaseCount('member', $originalMemberCount);
    }

    /** @return array<string, array{string, ?string, list<string>, string}> */
    public static function reservedMemberCodeUsernames(): array
    {
        return [
            'distributor' => ['DST', null, ['0081/0000/0000'], '0082/0000/0000'],
            'agent' => ['AGT', '0080/0000/0000', ['0080/0001/0000'], '0080/0002/0000'],
            'standalone agent' => ['AGT', null, ['0000/0001/0000'], '0000/0002/0000'],
            'direct reseller' => ['RSL', '0080/0000/0000', ['0080/0000/0001'], '0080/0000/0002'],
            'agent reseller' => ['RSL', '0080/0001/0000', ['0080/0001/0001'], '0080/0001/0002'],
            'consecutive reservations' => ['RSL', '0080/0000/0000', ['0080/0000/0001', '0080/0000/0002'], '0080/0000/0003'],
            'consecutive distributor reservations' => ['DST', null, ['0081/0000/0000', '0082/0000/0000'], '0083/0000/0000'],
            'consecutive agent reservations' => ['AGT', '0080/0000/0000', ['0080/0001/0000', '0080/0002/0000'], '0080/0003/0000'],
            'consecutive standalone agent reservations' => ['AGT', null, ['0000/0001/0000', '0000/0002/0000'], '0000/0003/0000'],
            'consecutive agent reseller reservations' => ['RSL', '0080/0001/0000', ['0080/0001/0001', '0080/0001/0002'], '0080/0001/0003'],
            'unused lower number' => ['RSL', '0080/0000/0000', ['0080/0000/9999'], '0080/0000/0001'],
            'another branch' => ['RSL', '0080/0000/0000', ['0081/0000/0001'], '0080/0000/0001'],
        ];
    }

    public function test_admin_approval_skips_a_username_belonging_to_a_different_member_code(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $distributorAccount] = $this->createMemberAccount('DST', '0080/0000/0000', 'distributor.sponsor');
        [$legacyMember, $legacyAccount] = $this->createMemberAccount(
            'RSL', 'LEGACY-RESELLER', '0080/0000/0001', '081234567899',
        );
        $originalMember = $legacyMember->fresh()->getAttributes();
        $originalAccount = $legacyAccount->fresh()->getAttributes();
        $administrator = $this->createAdministrator();

        foreach ([2, 3] as $sequence) {
            $this->actingAs($distributorAccount, 'member_api');
            $registrationId = $this->postJson('/api/v1/member/network/registrations', $this->registrationPayload(
                'reserved.username.'.$sequence, '08123456781'.$sequence, '357801010101001'.$sequence, 'RSL',
            ))->assertSuccessful()->json('data.id');
            $this->actingAs($administrator, 'admin_api');

            $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/approve")
                ->assertOk()->assertJsonPath('data.status.code', 'approved');

            $registration = MemberRegistration::query()->findOrFail($registrationId);
            $member = Member::query()->findOrFail($registration->member_registration_member_id);
            $this->assertSame('0080/0000/000'.$sequence, $member->member_code);
            $this->assertDatabaseHas('member_account', [
                'member_account_member_id' => $member->getKey(),
                'member_account_username' => $member->member_code,
            ]);
            $this->assertSame($originalMember, $legacyMember->fresh()->getAttributes());
            $this->assertSame($originalAccount, $legacyAccount->fresh()->getAttributes());

            $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/approve")
                ->assertUnprocessable()->assertJsonPath('error_code', 'process_error');
        }

        $this->assertDatabaseCount('member', 4);
        $this->assertDatabaseCount('member_account', 4);
    }

    public function test_approval_with_the_last_available_code_reserved_preserves_the_pending_registration(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        [$distributor, $distributorAccount] = $this->createMemberAccount('DST', '0080/0000/0000', 'distributor.sponsor');
        $this->createMemberAccount('RSL', 'LEGACY-RESELLER', '0080/0000/9999', '081234567899');
        $this->createNetworkMember('0080/0000/9998', $this->level('RSL'), $distributor);
        $this->actingAs($distributorAccount, 'member_api');
        $registrationId = $this->postJson('/api/v1/member/network/registrations', $this->registrationPayload(
            'reserved.exhausted', '081234567812', '3578010101010012', 'RSL',
        ))->assertSuccessful()->json('data.id');
        $this->actingAs($this->createAdministrator(), 'admin_api');

        $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nomor urut kode mitra telah mencapai batas maksimum.');

        $this->assertDatabaseCount('member', 3);
        $this->assertDatabaseCount('member_account', 2);
        $this->assertSame('requested', MemberRegistration::query()->findOrFail($registrationId)->member_registration_status);
        Mail::assertNotSent(MemberRegistrationApprovedMail::class);
    }

    public function test_registration_workflow_requires_authentication(): void
    {
        $this->getJson('/api/v1/member/network/registrations')->assertUnauthorized();
        $this->postJson('/api/v1/member/network/registrations')->assertUnauthorized();
        $this->getJson('/api/v1/admin/partnership/registrations')->assertUnauthorized();
        $this->postJson('/api/v1/admin/partnership/registrations')->assertUnauthorized();
    }

    public function test_registration_birth_date_validation_uses_natural_indonesian_message(): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $distributorAccount] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.validation',
        );
        $this->actingAs($distributorAccount, 'member_api');
        $payload = $this->registrationPayload(
            'agent.future-birth-date',
            '081234567809',
            '3578010101010009',
        );
        $payload['birth_date'] = today()->addDay()->toDateString();

        $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath(
                'errors.birth_date.0',
                'Tanggal lahir harus sebelum hari ini.',
            );
    }

    public function test_distributor_can_submit_agent_registration_and_only_view_own_requests(): void
    {
        Mail::fake();
        config()->set('initial_data.development_approval_password', 'Approve123');
        $this->createReferenceData();
        $this->createMemberGroups();
        [$distributor, $distributorAccount] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.one'
        );
        $this->actingAs($distributorAccount, 'member_api');

        $this->getJson('/api/v1/member/network/registrations/options')
            ->assertOk()
            ->assertJsonPath('data.target_level.code', 'AGT')
            ->assertJsonPath('data.target_level.name', 'Agen Utama')
            ->assertJsonPath('data.levels.0.code', 'AGT')
            ->assertJsonPath('data.levels.1.code', 'RSL')
            ->assertJsonCount(0, 'data.agents')
            ->assertJsonMissingPath('data.provinces')
            ->assertJsonMissingPath('data.banks');

        $payload = $this->registrationPayload('agent.one', '081234567801', '3578010101010001');
        $this->postJson('/api/v1/member/network/registrations', [
            ...$payload,
            'username' => 'agent.one',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors(['username']);

        $response = $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.level.code', 'AGT')
            ->assertJsonPath('data.sponsor.id', $distributor->getKey())
            ->assertJsonPath('data.submitted_by.id', $distributor->getKey())
            ->assertJsonMissingPath('data.upline')
            ->assertJsonPath('data.status.code', 'requested')
            ->assertJsonPath('data.status.label', 'Menunggu Verifikasi')
            ->assertJsonPath('data.identity.image_url', null)
            ->assertJsonMissingPath('data.applicant.password');
        $registrationId = $response->json('data.id');

        $this->getJson('/api/v1/member/network/registrations?search=agent.one')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.status.label', 'Menunggu Verifikasi');
        $this->getJson("/api/v1/member/network/registrations/{$registrationId}")
            ->assertOk()
            ->assertJsonPath('data.status.label', 'Menunggu Verifikasi')
            ->assertJsonPath('data.identity.number', '3578010101010001');

        [, $otherAccount] = $this->createMemberAccount(
            'DST',
            '0002/0000/0000',
            'distributor.two',
            '081234567899'
        );
        $this->actingAs($otherAccount, 'member_api');
        $this->getJson("/api/v1/member/network/registrations/{$registrationId}")
            ->assertNotFound();

        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'approved')
            ->assertJsonPath('data.status.label', 'Disetujui');

        $agent = Member::query()->where('member_mobilephone', '+6281234567801')->firstOrFail();
        $this->assertSame('0001/0001/0000', $agent->member_code);
        $this->assertSame($distributor->getKey(), $agent->member_parent_member_id);
        $agentAccount = MemberAccount::query()
            ->where('member_account_member_id', $agent->getKey())
            ->firstOrFail();
        Mail::assertSent(MemberRegistrationApprovedMail::class, function (MemberRegistrationApprovedMail $mail) use ($agentAccount): bool {
            return $mail->username === '0001/0001/0000'
                && $mail->password === 'Approve123'
                && Hash::check('Approve123', $agentAccount->member_account_password);
        });
        $this->assertDatabaseHas('member_account', [
            'member_account_member_id' => $agent->getKey(),
            'member_account_username' => '0001/0001/0000',
        ]);
    }

    public function test_recruitment_levels_follow_the_sponsor_level(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        [$distributor, $distributorAccount] = $this->createMemberAccount('DST', '0001/0000/0000', 'agent.distributor', '081234567899');
        [$agent, $agentAccount] = $this->createMemberAccount(
            'AGT',
            '0001/0001/0000',
            'agent.sponsor'
        );
        $agent->update(['member_parent_member_id' => $distributor->getKey()]);
        $this->actingAs($agentAccount, 'member_api');

        $this->getJson('/api/v1/member/network/registrations/options')
            ->assertOk()
            ->assertJsonPath('data.target_level.code', 'RSL')
            ->assertJsonPath('data.target_level.name', 'Reseller')
            ->assertJsonCount(1, 'data.levels');

        $payload = $this->registrationPayload('agent.invalid', '081234567802', '3578010101010002');
        $payload['level_id'] = $this->level('AGT')->getKey();
        $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonPath(
                'errors.level_id.0',
                'Tingkat mitra tidak diperbolehkan.'
            );

        $resellerPayload = $this->registrationPayload(
            'reseller.downline',
            '081234567806',
            '3578010101010006',
            'RSL',
        );
        $registrationId = $this->postJson(
            '/api/v1/member/network/registrations',
            $resellerPayload
        )->assertSuccessful()
            ->assertJsonPath('data.submitted_by.id', $agentAccount->member_account_member_id)
            ->assertJsonPath('data.next_approval', 'distributor')
            ->json('data.id');

        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/network/registration-approvals/{$registrationId}/approve")->assertOk();

        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status.code', 'approved');

        $reseller = Member::query()->where('member_mobilephone', '+6281234567806')->firstOrFail();
        $this->assertSame('0001/0001/0001', $reseller->member_code);
        $resellerAccount = MemberAccount::query()
            ->where('member_account_member_id', $reseller->getKey())
            ->firstOrFail();
        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson('/api/v1/member/network/registrations/options')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'process_error');
        $this->getJson('/api/v1/member/network/registrations')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'process_error');

        unset($payload['level_id']);
        $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertForbidden()
            ->assertJsonPath('error_code', 'process_error');
    }

    public function test_distributor_can_register_reseller_directly(): void
    {
        Mail::fake();
        config()->set('initial_data.development_approval_password', 'Approve123');
        $this->createReferenceData();
        $this->createMemberGroups();
        [$distributor, $distributorAccount] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.direct-reseller',
        );
        $this->actingAs($distributorAccount, 'member_api');

        $payload = $this->registrationPayload(
            'reseller.direct',
            '081234567818',
            '3578010101010018',
            'RSL',
        );
        $response = $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertSuccessful()
            ->assertJsonPath('data.level.code', 'RSL')
            ->assertJsonPath('data.sponsor.id', $distributor->getKey())
            ->assertJsonPath('data.submitted_by.id', $distributor->getKey())
            ->assertJsonPath('data.network_distributor.id', $distributor->getKey());

        $this->assertDatabaseHas('member_registration', [
            'member_registration_id' => $response->json('data.id'),
            'member_registration_member_level_id' => $this->level('RSL')->getKey(),
            'member_registration_upline_member_id' => $distributor->getKey(),
            'member_registration_submitter_member_id' => $distributor->getKey(),
        ]);

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson(
            "/api/v1/admin/partnership/registrations/{$response->json('data.id')}/approve",
        )->assertOk();

        $this->assertDatabaseHas('member', [
            'member_code' => '0001/0000/0001',
            'member_member_level_id' => $this->level('RSL')->getKey(),
            'member_parent_member_id' => $distributor->getKey(),
        ]);
    }

    public function test_distributor_can_choose_own_agent_when_registering_reseller(): void
    {
        Mail::fake();
        config()->set('initial_data.development_approval_password', 'Approve123');
        $this->createReferenceData();
        $this->createMemberGroups();
        [$distributor, $distributorAccount] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.reseller',
        );
        $agent = $this->createNetworkMember(
            '0001/0001/0000',
            $this->level('AGT'),
            $distributor,
        );
        [$otherDistributor] = $this->createMemberAccount(
            'DST',
            '0002/0000/0000',
            'distributor.other-reseller',
            '081234567819',
        );
        $otherAgent = $this->createNetworkMember(
            '0002/0001/0000',
            $this->level('AGT'),
            $otherDistributor,
        );
        $this->actingAs($distributorAccount, 'member_api');

        $this->getJson('/api/v1/member/network/registrations/options')
            ->assertOk()
            ->assertJsonCount(1, 'data.agents')
            ->assertJsonPath('data.agents.0.id', $agent->getKey())
            ->assertJsonPath('data.agents.0.code', '0001/0001/0000');

        $payload = $this->registrationPayload(
            'reseller.under-agent',
            '081234567818',
            '3578010101010018',
            'RSL',
        );
        $this->postJson('/api/v1/member/network/registrations', [
            ...$payload,
            'upline_member_id' => $otherAgent->getKey(),
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.upline_member_id.0',
                'Agen Utama tidak aktif atau bukan bagian dari jaringan Distributor.',
            );

        $response = $this->postJson(
            '/api/v1/member/network/registrations',
            [
                ...$payload,
                'upline_member_id' => $agent->getKey(),
            ],
        )->assertSuccessful()
            ->assertJsonPath('data.level.code', 'RSL')
            ->assertJsonPath('data.sponsor.id', $agent->getKey())
            ->assertJsonPath('data.submitted_by.id', $distributor->getKey())
            ->assertJsonPath('data.submitted_by.level_code', 'DST')
            ->assertJsonPath('data.network_distributor.id', $distributor->getKey());

        $this->assertDatabaseHas('member_registration', [
            'member_registration_id' => $response->json('data.id'),
            'member_registration_member_level_id' => $this->level('RSL')->getKey(),
            'member_registration_upline_member_id' => $agent->getKey(),
            'member_registration_submitter_member_id' => $distributor->getKey(),
        ]);
        $this->getJson('/api/v1/member/network/registrations?search=reseller.under-agent')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.sponsor.id', $agent->getKey());
        $this->getJson("/api/v1/member/network/registrations/{$response->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.sponsor.id', $agent->getKey())
            ->assertJsonPath('data.submitted_by.id', $distributor->getKey());

        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->getJson(
            "/api/v1/admin/partnership/registrations/{$response->json('data.id')}",
        )->assertOk()
            ->assertJsonPath('data.submitted_by.id', $distributor->getKey())
            ->assertJsonPath('data.submitted_by.name', $distributor->member_name)
            ->assertJsonPath('data.sponsor.id', $agent->getKey());
        $this->postJson(
            "/api/v1/admin/partnership/registrations/{$response->json('data.id')}/approve",
        )->assertOk();

        $this->assertDatabaseHas('member', [
            'member_code' => '0001/0001/0001',
            'member_member_level_id' => $this->level('RSL')->getKey(),
            'member_parent_member_id' => $agent->getKey(),
        ]);
    }

    public function test_admin_can_create_and_approve_distributor_registration_atomically(): void
    {
        Mail::fake();
        config()->set('initial_data.development_approval_password', 'Approve123');
        $this->createReferenceData();
        $this->createMemberGroups();
        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');

        $response = $this->postJson(
            '/api/v1/admin/partnership/registrations',
            $this->registrationPayload('distributor.new', '081234567803', '3578010101010003', null)
        )->assertSuccessful()
            ->assertJsonPath('data.level.code', 'DST')
            ->assertJsonPath('data.sponsor', null)
            ->assertJsonPath('data.status.code', 'requested');
        $registrationId = $response->json('data.id');

        $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/approve", [
            'note' => 'Dokumen telah diverifikasi.',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'approved')
            ->assertJsonPath('data.processed_by.id', $administrator->getKey());

        $member = Member::query()->where('member_mobilephone', '+6281234567803')->firstOrFail();
        $this->assertSame('0001/0000/0000', $member->member_code, 'Kode member pada database tidak sesuai.');
        $this->assertSame('DST', $member->level->member_level_code);
        $account = MemberAccount::query()
            ->where('member_account_member_id', $member->getKey())
            ->firstOrFail();
        $this->assertSame('Distributor', $account->group->member_group_name);
        Mail::assertSent(MemberRegistrationApprovedMail::class, function (MemberRegistrationApprovedMail $mail) use ($account): bool {
            return $mail->username === '0001/0000/0000'
                && $mail->password === 'Approve123'
                && Hash::check('Approve123', $account->member_account_password);
        });
        $this->assertDatabaseHas('member_address', [
            'member_address_member_id' => $member->getKey(),
            'member_address_is_default' => 1,
        ]);
        $this->assertDatabaseHas('member_bank_account', [
            'member_bank_account_member_id' => $member->getKey(),
            'member_bank_account_bank_id' => 1,
        ]);
        $this->assertDatabaseHas('member_history', [
            'member_history_member_id' => $member->getKey(),
            'member_history_action' => 'register',
            'member_history_to_level_id' => $this->level('DST')->getKey(),
            'member_history_approved_by' => $administrator->getKey(),
        ]);

        $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/approve")
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Pengajuan sudah diproses dan tidak dapat diproses kembali.');

    }

    public function test_sponsor_with_scheduled_downgrade_cannot_submit_registration(): void
    {
        $this->createReferenceData();
        $this->createMemberGroups();
        [$distributor, $distributorAccount] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.downgrade',
        );
        MemberNetworkSwitch::query()->create([
            'network_switch_upgrade_qualified_id' => 0,
            'network_switch_member_id' => $distributor->getKey(),
            'network_switch_from_parent_member_id' => 0,
            'network_switch_to_parent_member_id' => 0,
            'network_switch_from_level_id' => $this->level('DST')->getKey(),
            'network_switch_to_level_id' => $this->level('AGT')->getKey(),
            'network_switch_type' => 'downgrade',
            'network_switch_status' => 'scheduled',
            'network_switch_admin_id' => 0,
            'network_switch_approved_datetime' => now(),
            'network_switch_effective_date' => now()->addMonthNoOverflow()->startOfMonth(),
            'network_switch_applied_datetime' => null,
            'network_switch_transfer_note' => 'Downgrade terjadwal.',
            'network_switch_created_datetime' => now(),
        ]);
        $this->actingAs($distributorAccount, 'member_api');

        $this->postJson(
            '/api/v1/member/network/registrations',
            $this->registrationPayload('agent.blocked', '081234567807', '3578010101010007'),
        )->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath(
                'message',
                'Mitra yang sedang dijadwalkan turun tingkat tidak dapat menerima pendaftaran mitra baru.',
            );

        $this->assertDatabaseMissing('member_registration', [
            'member_registration_username' => 'agent.blocked',
        ]);
    }

    public function test_admin_can_reject_registration_and_inactive_sponsor_blocks_approval(): void
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        $administrator = $this->createAdministrator();
        [$distributor, $distributorAccount] = $this->createMemberAccount(
            'DST',
            '0001/0000/0000',
            'distributor.sponsor'
        );

        $this->actingAs($distributorAccount, 'member_api');
        $payload = $this->registrationPayload('agent.pending', '081234567804', '3578010101010004');
        $registrationId = $this->postJson('/api/v1/member/network/registrations', $payload)
            ->assertSuccessful()
            ->json('data.id');

        $this->actingAs($administrator, 'admin_api');
        $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/reject", [])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'validation')
            ->assertJsonValidationErrors(['note']);
        $this->postJson("/api/v1/admin/partnership/registrations/{$registrationId}/reject", [
            'note' => 'Dokumen identitas tidak terbaca.',
        ])->assertOk()
            ->assertJsonPath('data.status.code', 'rejected')
            ->assertJsonPath('data.status.label', 'Ditolak');
        Mail::assertSent(MemberRegistrationRejectedMail::class, function (MemberRegistrationRejectedMail $mail): bool {
            return $mail->rejectionReason === 'Dokumen identitas tidak terbaca.';
        });
        $this->assertDatabaseMissing('member_account', ['member_account_member_id' => 2]);

        $this->actingAs($distributorAccount, 'member_api');
        $secondPayload = $this->registrationPayload(
            'agent.inactive-sponsor',
            '081234567805',
            '3578010101010005'
        );
        $secondRegistrationId = $this->postJson(
            '/api/v1/member/network/registrations',
            $secondPayload
        )->assertSuccessful()->json('data.id');
        $distributor->update(['member_status' => 2]);

        $this->actingAs($administrator, 'admin_api');
        $this->postJson(
            "/api/v1/admin/partnership/registrations/{$secondRegistrationId}/approve"
        )->assertUnprocessable()
            ->assertJsonPath('error_code', 'process_error')
            ->assertJsonPath('message', 'Sponsor tidak aktif atau tidak tersedia.');
        $this->assertDatabaseMissing('member_account', [
            'member_account_member_id' => 2,
        ]);
    }

    public function test_agent_registration_is_hidden_from_admin_until_distributor_approval(): void
    {
        [$registration, $distributor, $distributorAccount, $agent, $agentAccount] = $this->createAgentRegistration();
        $id = $registration->getKey();
        $this->assertDatabaseCount('member', 2);
        $this->assertDatabaseCount('member_account', 2);
        $this->assertSame(0, $this->registrationApprovalCount());
        $this->getJson("/api/v1/member/network/registrations/{$id}")->assertOk()
            ->assertJsonPath('data.status.label', 'Menunggu Verifikasi')
            ->assertJsonPath('data.next_approval', 'distributor')
            ->assertJsonPath('data.distributor_approval.status.code', 'pending')
            ->assertJsonPath('data.distributor_approval.distributor.id', $distributor->getKey());

        $administrator = $this->createAdministrator();
        $this->actingAs($administrator, 'admin_api');
        $this->getJson('/api/v1/admin/partnership/registrations?filter[status]=requested')
            ->assertOk()->assertJsonCount(0, 'data.results')->assertJsonPath('data.pagination.total_data', 0);
        $this->getJson("/api/v1/admin/partnership/registrations/{$id}")->assertNotFound();
        foreach (['approve', 'reject'] as $action) {
            $this->postJson("/api/v1/admin/partnership/registrations/{$id}/{$action}", ['note' => 'Admin mencoba langsung'])
                ->assertUnprocessable()->assertJsonPath('message', 'Pengajuan harus disetujui Distributor terlebih dahulu.');
        }

        $this->actingAs($distributorAccount, 'member_api');
        $this->getJson('/api/v1/member/network/registration-approvals?filter[distributor_approval_status]=pending')
            ->assertOk()->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.distributor_approval.status.code', 'pending');
        $this->getJson("/api/v1/member/network/registration-approvals/{$id}")->assertOk()
            ->assertJsonPath('data.payment_proof_image_url', 'https://example.test/agent-transfer.jpg');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve", ['note' => 'Dokumen sudah diperiksa'])
            ->assertOk()->assertJsonPath('data.status.code', 'requested')
            ->assertJsonPath('data.status.label', 'Menunggu Verifikasi')
            ->assertJsonPath('data.next_approval', 'admin')
            ->assertJsonPath('data.distributor_approval.status.code', 'approved')
            ->assertJsonPath('data.distributor_approval.note', 'Dokumen sudah diperiksa')
            ->assertJsonPath('data.processed_by', null);
        $this->assertDatabaseCount('member_account', 2);
        Mail::assertNothingSent();
        $this->assertSame(1, $this->registrationApprovalCount());
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")->assertUnprocessable();
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/reject", ['note' => 'Ulang'])->assertUnprocessable();
        $this->getJson('/api/v1/member/network/registration-approvals?filter[distributor_approval_status]=pending')
            ->assertOk()->assertJsonCount(0, 'data.results');

        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/network/registrations/{$id}")->assertOk()->assertJsonPath('data.next_approval', 'admin');
        $this->actingAs($administrator, 'admin_api');
        $this->getJson('/api/v1/admin/partnership/registrations?filter[status]=requested')
            ->assertOk()->assertJsonCount(1, 'data.results');
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")->assertOk()
            ->assertJsonPath('data.status.code', 'approved')->assertJsonPath('data.next_approval', null)
            ->assertJsonPath('data.distributor_approval.status.code', 'approved');
        $this->assertDatabaseCount('member_account', 3);
        $this->assertSame(0, $this->registrationApprovalCount());
        $this->assertDatabaseHas('member', ['member_mobilephone' => '+6281234567891', 'member_parent_member_id' => $agent->getKey()]);
        Mail::assertSent(MemberRegistrationApprovedMail::class, 1);
    }

    public function test_distributor_cannot_access_another_distributors_registration_approval(): void
    {
        [$registration, $distributor, $distributorAccount] = $this->createAgentRegistration();
        [, $otherAccount] = $this->createMemberAccount('DST', '0002/0000/0000', 'other.distributor', '081234567892');
        $id = $registration->getKey();
        $this->actingAs($otherAccount, 'member_api');
        $this->getJson('/api/v1/member/network/registration-approvals')->assertOk()->assertJsonCount(0, 'data.results');
        $this->getJson("/api/v1/member/network/registration-approvals/{$id}")->assertNotFound();
        foreach (['approve', 'reject'] as $action) {
            $this->postJson("/api/v1/member/network/registration-approvals/{$id}/{$action}", ['note' => 'Bukan jaringan saya'])
                ->assertNotFound();
        }
        $this->assertSame('pending', $registration->fresh()->member_registration_distributor_approval_status);
        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve", [
            'note' => 'Sesuai', 'distributor_approval_status' => 'rejected', 'distributor_member_id' => 999,
        ])->assertOk()->assertJsonPath('data.distributor_approval.status.code', 'approved')
            ->assertJsonPath('data.distributor_approval.distributor.id', $distributor->getKey());
    }

    public function test_distributor_rejection_requires_a_reason_and_does_not_reach_admin(): void
    {
        [$registration, , $distributorAccount, , $agentAccount] = $this->createAgentRegistration();
        $id = $registration->getKey();
        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/reject", [])
            ->assertUnprocessable()->assertJsonValidationErrors('note')
            ->assertJsonPath('errors.note.0', 'Alasan penolakan wajib diisi.');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve", ['note' => str_repeat('a', 1001)])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/reject", ['note' => 'Bukti transfer belum sesuai'])
            ->assertOk()->assertJsonPath('data.status.code', 'rejected')
            ->assertJsonPath('data.distributor_approval.status.code', 'rejected')
            ->assertJsonPath('data.distributor_approval.note', 'Bukti transfer belum sesuai')
            ->assertJsonPath('data.next_approval', null);
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")->assertUnprocessable();
        $this->assertDatabaseCount('member_account', 2);
        $this->assertSame(0, $this->registrationApprovalCount());
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->getJson('/api/v1/admin/partnership/registrations')->assertOk()->assertJsonCount(0, 'data.results');
        $this->getJson("/api/v1/admin/partnership/registrations/{$id}")->assertNotFound();
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")->assertUnprocessable();
        $this->actingAs($agentAccount, 'member_api');
        $this->getJson("/api/v1/member/network/registrations/{$id}")->assertOk()
            ->assertJsonPath('data.note', 'Bukti transfer belum sesuai');
        $this->postJson('/api/v1/member/network/registrations', $this->registrationPayload('review.applicant', '081234567891', '3578010101010091', 'RSL'))
            ->assertSuccessful()->assertJsonPath('data.distributor_approval.status.code', 'pending');
        Mail::assertNothingSent();
    }

    public function test_registration_approval_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/member/network/registration-approvals')->assertUnauthorized();
        $this->getJson('/api/v1/member/network/registration-approvals/1')->assertUnauthorized();
        $this->postJson('/api/v1/member/network/registration-approvals/1/approve')->assertUnauthorized();
        $this->postJson('/api/v1/member/network/registration-approvals/1/reject')->assertUnauthorized();
    }

    public function test_agents_and_resellers_cannot_use_distributor_approval_endpoints(): void
    {
        [$registration] = $this->createAgentRegistration();
        $id = $registration->getKey();
        $this->getJson('/api/v1/member/network/registration-approvals')->assertForbidden();
        $this->getJson("/api/v1/member/network/registration-approvals/{$id}")->assertForbidden();
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")->assertForbidden();
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/reject", ['note' => 'Ditolak'])->assertForbidden();
        [, $resellerAccount] = $this->createMemberAccount('RSL', '0001/0001/0001', 'review.reseller', '081234567893');
        $this->actingAs($resellerAccount, 'member_api');
        $this->getJson('/api/v1/member/network/registration-approvals')->assertForbidden();
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")->assertForbidden();
    }

    public function test_distributor_approval_rechecks_network_and_active_accounts(): void
    {
        [$registration, $distributor, $distributorAccount, $agent] = $this->createAgentRegistration();
        $id = $registration->getKey();
        $agent->update(['member_parent_member_id' => 0]);
        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")
            ->assertUnprocessable()->assertJsonPath('message', 'Agen Utama bukan bagian dari jaringan Distributor.');
        $agent->update(['member_parent_member_id' => $distributor->getKey(), 'member_status' => 2]);
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")->assertUnprocessable();
        $distributor->update(['member_status' => 2]);
        $distributorAccount->unsetRelation('member');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")->assertForbidden();
        $this->assertSame('pending', $registration->fresh()->member_registration_distributor_approval_status);
        $this->assertDatabaseCount('member_account', 2);
    }

    public function test_agent_cannot_submit_without_an_active_distributor_or_forge_approval_fields(): void
    {
        [$registration, $distributor, , $agent, $agentAccount] = $this->createAgentRegistration();
        $this->assertSame('pending', $registration->member_registration_distributor_approval_status);
        $this->assertSame((int) $distributor->getKey(), (int) $registration->member_registration_distributor_member_id);
        $agent->update(['member_parent_member_id' => 0]);
        $this->actingAs($agentAccount, 'member_api');
        $this->postJson('/api/v1/member/network/registrations', $this->registrationPayload('missing.distributor', '081234567894', '3578010101010094', 'RSL'))
            ->assertUnprocessable()->assertJsonPath('message', 'Distributor Agen Utama tidak aktif atau tidak tersedia.');
        $this->assertDatabaseCount('member_registration', 1);
    }

    public function test_admin_cannot_approve_after_the_agent_moves_away_from_the_approving_distributor(): void
    {
        [$registration, , $distributorAccount, $agent] = $this->createAgentRegistration();
        $id = $registration->getKey();
        $this->actingAs($distributorAccount, 'member_api');
        $this->postJson("/api/v1/member/network/registration-approvals/{$id}/approve")->assertOk();
        $agent->update(['member_parent_member_id' => 0]);
        $this->actingAs($this->createAdministrator(), 'admin_api');
        $this->postJson("/api/v1/admin/partnership/registrations/{$id}/approve")
            ->assertUnprocessable()->assertJsonPath('message', 'Jaringan Agen Utama berubah. Ajukan pendaftaran kembali untuk persetujuan Distributor.');
        $this->assertDatabaseCount('member_account', 2);
        $this->assertSame('requested', $registration->fresh()->member_registration_status);
        Mail::assertNothingSent();
    }

    /** @return array{MemberRegistration, Member, MemberAccount, Member, MemberAccount} */
    private function createAgentRegistration(): array
    {
        Mail::fake();
        $this->createReferenceData();
        $this->createMemberGroups();
        [$distributor, $distributorAccount] = $this->createMemberAccount('DST', '0001/0000/0000', 'review.distributor', '081234567890');
        [$agent, $agentAccount] = $this->createMemberAccount('AGT', '0001/0001/0000', 'review.agent', '081234567889');
        $agent->update(['member_parent_member_id' => $distributor->getKey()]);
        $this->actingAs($agentAccount, 'member_api');
        $id = $this->postJson('/api/v1/member/network/registrations', [
            ...$this->registrationPayload('review.applicant', '081234567891', '3578010101010091', 'RSL'),
            'payment_proof_image_url' => 'https://example.test/agent-transfer.jpg',
            'distributor_approval_status' => 'approved', 'distributor_member_id' => 999,
        ])->assertSuccessful()->json('data.id');

        return [MemberRegistration::query()->findOrFail($id), $distributor, $distributorAccount, $agent, $agentAccount];
    }

    private function registrationApprovalCount(): int
    {
        $summary = app(AdminActionSummaryService::class)->summary();

        return collect($summary['menus'])->firstWhere('route', '/admin/partnership/registration-approvals')['count'];
    }

    private function createReferenceData(): void
    {
        DB::table('ref_country')->insert([
            'country_id' => 1,
            'country_iso_code' => 'ID',
            'country_phone_code' => '+62',
            'country_name' => 'Indonesia',
            'country_flag' => '',
            'country_is_active' => '1',
        ]);
        DB::table('ref_province')->insert([
            'province_id' => '11',
            'province_name' => 'Jawa Timur',
        ]);
        DB::table('ref_city')->insert([
            'city_id' => '1101',
            'city_province_id' => '11',
            'city_name' => 'Surabaya',
            'city_type' => 'Kota',
        ]);
        DB::table('ref_district')->insert([
            'district_id' => '110101',
            'district_city_id' => '1101',
            'district_name' => 'Tegalsari',
        ]);
        DB::table('ref_subdistrict')->insert([
            'subdistrict_id' => 1,
            'subdistrict_district_id' => 110101,
            'subdistrict_name' => 'Keputran',
            'subdistrict_zip_code' => 60265,
        ]);
        DB::table('ref_bank')->insert([
            'bank_id' => 1,
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'bank_logo' => 'bca.png',
            'bank_is_active' => 1,
        ]);
    }

    private function createMemberGroups(): void
    {
        foreach ([1 => 'Distributor', 2 => 'Agen Utama', 3 => 'Reseller'] as $id => $name) {
            $group = new MemberGroup;
            $group->member_group_id = $id;
            $group->fill([
                'member_group_name' => $name,
                'member_group_description' => "Akses {$name}",
                'member_group_is_active' => 1,
            ])->save();
        }
    }

    /** @return array{Member, MemberAccount} */
    private function createMemberAccount(
        string $levelCode,
        string $memberCode,
        string $username,
        string $mobilePhone = '081234567800'
    ): array {
        $level = $this->level($levelCode);
        $member = Member::query()->create([
            'member_code' => $memberCode,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => 0,
            'member_name' => "Sponsor {$levelCode}",
            'member_email' => "{$username}@example.test",
            'member_mobilephone' => $mobilePhone,
            'member_identity_no' => "ID-{$username}",
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $account = MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_member_group_id' => $level->getKey(),
            'member_account_username' => $username,
            'member_account_password' => Hash::make('Secret123'),
            'member_account_pin' => '123456',
        ]);

        return [$member, $account];
    }

    private function createNetworkMember(string $code, MemberLevel $level, ?Member $sponsor): Member
    {
        $sequence = Member::query()->count() + 1;

        return Member::query()->create([
            'member_code' => $code,
            'member_member_level_id' => $level->getKey(),
            'member_parent_member_id' => $sponsor?->getKey() ?? 0,
            'member_name' => "Mitra {$code}",
            'member_email' => "mitra.{$sequence}@example.test",
            'member_mobilephone' => '+628'.str_pad((string) $sequence, 10, '0', STR_PAD_LEFT),
            'member_identity_no' => str_pad((string) $sequence, 16, '0', STR_PAD_LEFT),
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
    }

    private function createAdministrator(): SiteAdministrator
    {
        $group = SiteAdministratorGroup::query()->create([
            'administrator_group_title' => 'Super Administrator',
            'administrator_group_type' => 'superuser',
            'administrator_group_is_active' => 1,
        ]);

        return SiteAdministrator::query()->create([
            'administrator_administrator_group_id' => $group->getKey(),
            'administrator_username' => 'registration.admin',
            'administrator_password' => Hash::make('Secret123'),
            'administrator_name' => 'Registration Admin',
            'administrator_email' => 'registration.admin@example.test',
            'administrator_image' => '',
            'administrator_is_active' => 1,
        ]);
    }

    private function level(string $code): MemberLevel
    {
        return MemberLevel::query()->where('member_level_code', $code)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function registrationPayload(
        string $username,
        string $mobilePhone,
        string $identityNo,
        ?string $levelCode = 'AGT',
    ): array {
        $payload = [
            'name' => 'Calon Mitra',
            'email' => "{$username}@example.test",
            'mobile_phone' => $mobilePhone,
            'gender' => 'Laki-laki',
            'birth_date' => '1995-01-01',
            'address' => 'Jalan DNY Nomor 1',
            'province_id' => '11',
            'city_id' => '1101',
            'district_id' => '110101',
            'subdistrict_id' => 1,
            'country_id' => 1,
            'bank_id' => 1,
            'bank_account_name' => 'Calon Mitra',
            'bank_account_number' => '1234567890',
            'bank_city' => 'Surabaya',
            'bank_branch' => 'Tegalsari',
            'identity_type' => 'KTP',
            'identity_no' => $identityNo,
            'nib' => null,
        ];

        if ($levelCode !== null) {
            $payload['level_id'] = $this->level($levelCode)->getKey();
        }

        return $payload;
    }
}
