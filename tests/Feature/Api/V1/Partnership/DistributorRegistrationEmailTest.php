<?php

namespace Tests\Feature\Api\V1\Partnership;

use App\Mail\DistributorRegistrationReviewMail;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberLevel;
use App\Models\MemberRegistration;
use App\Services\Partnership\MemberRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DistributorRegistrationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_registration_queues_review_for_assigned_distributor(): void
    {
        Mail::fake();
        $distributor = $this->member('DST', 'distributor');
        $agent = $this->member('AGT', 'agent', $distributor);
        $this->member('DST', 'unrelated');
        $registration = $this->register($agent);

        Mail::assertQueued(DistributorRegistrationReviewMail::class, function ($mail) use ($distributor, $registration): bool {
            $this->assertTrue($mail->afterCommit);
            $this->assertSame('Calon Reseller', $mail->applicantName);
            $this->assertSame(url("/member/network/registration-approvals/{$registration->getKey()}"), $mail->reviewUrl);

            return $mail->hasTo($distributor->member_email);
        });
        Mail::assertQueuedCount(1);
    }

    public function test_review_email_renders_applicant_and_approval_link(): void
    {
        $mail = new DistributorRegistrationReviewMail(
            distributorName: 'Distributor Test',
            applicantName: 'Calon Reseller',
            agentName: 'Agen Test',
            registrationId: 'REG-000123',
            reviewUrl: url('/member/network/registration-approvals/123'),
        );
        $mail->assertSeeInHtml('Distributor Test');
        $mail->assertSeeInHtml('Calon Reseller');
        $mail->assertSeeInHtml('Agen Test');
        $mail->assertSeeInHtml('REG-000123');
        $mail->assertSeeInHtml(url('/member/network/registration-approvals/123'));
    }

    public function test_distributor_submission_does_not_queue_distributor_review(): void
    {
        Mail::fake();
        $registration = $this->register($this->member('DST', 'direct'));
        $this->assertSame('not_required', $registration->member_registration_distributor_approval_status);
        Mail::assertNothingOutgoing();
    }

    private function member(string $levelCode, string $name, ?Member $parent = null): Member
    {
        $level = MemberLevel::query()->where('member_level_code', $levelCode)->firstOrFail();

        return Member::query()->create([
            'member_code' => $name, 'member_name' => $name, 'member_email' => "{$name}@example.test",
            'member_member_level_id' => $level->getKey(), 'member_parent_member_id' => $parent?->getKey() ?? 0,
            'member_status' => 1, 'member_join_datetime' => now(),
        ]);
    }

    private function register(Member $submitter): MemberRegistration
    {
        $account = new MemberAccount(['member_account_member_id' => $submitter->getKey()]);

        return app(MemberRegistrationService::class)->createByMember($account, [
            'level_id' => MemberLevel::query()->where('member_level_code', 'RSL')->value('member_level_id'),
            'name' => 'Calon Reseller', 'email' => 'applicant@example.test', 'mobile_phone' => '+6281234567890',
            'gender' => 'Perempuan', 'birth_date' => '1995-01-01', 'address' => 'Jalan Test',
            'province_id' => '11', 'city_id' => '1101', 'district_id' => '110101', 'subdistrict_id' => 1,
            'identity_type' => 'KTP', 'identity_no' => '0000000000001234',
        ]);
    }
}
