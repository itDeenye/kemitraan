<?php

namespace Tests\Feature\Api\V1\Partnership;

use App\Mail\MemberCredentialsMail;
use App\Mail\MemberRegistrationApprovedMail;
use App\Mail\MemberRegistrationRejectedMail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberGroup;
use App\Models\MemberLevel;
use App\Models\MemberNetworkSwitch;
use App\Models\SiteAdministrator;
use App\Models\SiteAdministratorGroup;
use App\Services\Partnership\MemberCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonPath('data.target_level.name', 'Agent')
            ->assertJsonMissingPath('data.levels')
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
            ->assertJsonMissingPath('data.upline')
            ->assertJsonPath('data.status.code', 'requested')
            ->assertJsonPath('data.identity.image_url', null)
            ->assertJsonMissingPath('data.applicant.password');
        $registrationId = $response->json('data.id');

        $this->getJson('/api/v1/member/network/registrations?search=agent.one')
            ->assertOk()
            ->assertJsonCount(1, 'data.results');
        $this->getJson("/api/v1/member/network/registrations/{$registrationId}")
            ->assertOk()
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
            ->assertJsonPath('data.status.code', 'approved');

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
        $this->createReferenceData();
        $this->createMemberGroups();
        [, $agentAccount] = $this->createMemberAccount(
            'AGT',
            '0001/0001/0000',
            'agent.sponsor'
        );
        $this->actingAs($agentAccount, 'member_api');

        $this->getJson('/api/v1/member/network/registrations/options')
            ->assertOk()
            ->assertJsonPath('data.target_level.code', 'RSL')
            ->assertJsonPath('data.target_level.name', 'Reseller')
            ->assertJsonMissingPath('data.levels');

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
            '3578010101010006'
        );
        $registrationId = $this->postJson(
            '/api/v1/member/network/registrations',
            $resellerPayload
        )->assertSuccessful()->json('data.id');

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
            $this->registrationPayload('distributor.new', '081234567803', '3578010101010003')
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
            ->assertJsonPath('data.status.code', 'rejected');
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
        foreach ([1 => 'Distributor', 2 => 'Agent', 3 => 'Reseller'] as $id => $name) {
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
    private function registrationPayload(string $username, string $mobilePhone, string $identityNo): array
    {
        return [
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
    }
}
