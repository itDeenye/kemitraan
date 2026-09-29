<?php

namespace Tests\Feature\Services;

use App\Models\Member;
use App\Models\MemberAccount;
use App\Services\Partnership\MemberPasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberPasswordServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_approval_password_uses_member_birth_date(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $password = app(MemberPasswordService::class)->approvalPassword('1990-12-31');

            $this->assertSame('31121990', $password);
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    public function test_it_recognizes_only_the_current_birth_date_password(): void
    {
        $member = Member::query()->create([
            'member_code' => 'MEMBER-PASSWORD-TEST',
            'member_name' => 'Member Password Test',
            'member_birth_date' => '1990-12-31',
            'member_join_datetime' => now(),
            'member_status' => 1,
        ]);
        $account = MemberAccount::query()->create([
            'member_account_member_id' => $member->getKey(),
            'member_account_username' => 'member.password.test',
            'member_account_password' => Hash::make('31121990'),
        ]);
        $service = app(MemberPasswordService::class);

        $this->assertTrue($service->isBirthDatePassword($account->load('member')));

        $account->update(['member_account_password' => Hash::make('PasswordBaru123')]);
        $this->assertFalse($service->isBirthDatePassword($account->refresh()->load('member')));

        $member->update(['member_birth_date' => null]);
        $this->assertFalse($service->isBirthDatePassword($account->refresh()->load('member')));
    }
}
