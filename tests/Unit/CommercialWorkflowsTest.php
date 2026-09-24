<?php

namespace Tests\Unit;

use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Modules\Commercial\Application\CommercialWorkflows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class CommercialWorkflowsTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    private CommercialWorkflows $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflows = app(CommercialWorkflows::class);
    }

    // ---------------------------------------------------------------------
    // assignMembership: date overlap
    // ---------------------------------------------------------------------

    public function test_renewal_does_not_overlap_the_latest_membership(): void
    {
        $f = $this->fixture();

        $first = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $second = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);

        $this->assertSame($first->ends_at->addDay()->toDateString(), $second->starts_at->toDateString());
        $this->assertTrue($second->starts_at->gt($first->ends_at));
    }

    public function test_renewal_with_a_later_start_keeps_the_requested_start(): void
    {
        $f = $this->fixture();

        $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $later = today()->addDays(40)->toDateString();

        $second = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => $later], $f['request']);

        $this->assertSame($later, $second->starts_at->toDateString());
    }

    public function test_renewal_ignores_cancelled_memberships_when_computing_overlap(): void
    {
        $f = $this->fixture();
        $first = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $first->update(['status' => 'cancelled']);

        $second = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);

        // The cancelled membership no longer occupies the window.
        $this->assertSame(today()->toDateString(), $second->starts_at->toDateString());
    }

    // ---------------------------------------------------------------------
    // assignMembership: plan and discount validation
    // ---------------------------------------------------------------------

    public function test_inactive_plan_is_rejected(): void
    {
        $f = $this->fixture(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $this->workflows->assignMembership($f['member'], $f['plan'], [], $f['request']);
    }

    public function test_plan_from_another_branch_is_rejected(): void
    {
        $f = $this->fixture();
        $otherBranch = $this->createBranch($f['gym']);
        $plan = MembershipPlan::factory()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        $this->workflows->assignMembership($f['member'], $plan, [], $f['request']);
    }

    public function test_discount_greater_than_price_is_rejected(): void
    {
        $f = $this->fixture();

        $this->expectException(ValidationException::class);
        $this->workflows->assignMembership($f['member'], $f['plan'], ['discount_amount' => 150], $f['request']);
    }

    public function test_negative_discount_is_rejected(): void
    {
        $f = $this->fixture();

        $this->expectException(ValidationException::class);
        $this->workflows->assignMembership($f['member'], $f['plan'], ['discount_amount' => -5], $f['request']);
    }

    public function test_membership_amounts_and_dates_are_computed(): void
    {
        $f = $this->fixture(['duration_days' => 30, 'price' => 100]);

        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['discount_amount' => 20], $f['request']);

        $this->assertSame('active', $membership->status);
        $this->assertSame(today()->toDateString(), $membership->starts_at->toDateString());
        $this->assertSame(today()->addDays(29)->toDateString(), $membership->ends_at->toDateString());
        $this->assertSame('100.00', (string) $membership->agreed_price);
        $this->assertSame('20.00', (string) $membership->discount_amount);
        $this->assertSame('80.00', (string) $membership->payable_amount);
        $this->assertSame('0.00', (string) $membership->paid_amount);
        $this->assertSame($f['plan']->id, $membership->membership_plan_id);
    }

    public function test_future_membership_starts_as_pending(): void
    {
        $f = $this->fixture();

        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->addDays(10)->toDateString()], $f['request']);

        $this->assertSame('pending', $membership->status);
    }

    // ---------------------------------------------------------------------
    // checkIn: freeze / cancel
    // ---------------------------------------------------------------------

    public function test_check_in_is_rejected_when_membership_is_frozen(): void
    {
        $f = $this->fixture();
        $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        MemberMembership::query()->where('member_id', $f['member']->id)->update(['status' => 'frozen']);

        $this->expectException(ValidationException::class);
        $this->workflows->checkIn($f['member'], 'manual', $f['request']);
    }

    public function test_check_in_is_rejected_when_membership_is_cancelled(): void
    {
        $f = $this->fixture();
        $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        MemberMembership::query()->where('member_id', $f['member']->id)->update(['status' => 'cancelled']);

        $this->expectException(ValidationException::class);
        $this->workflows->checkIn($f['member'], 'manual', $f['request']);
    }

    public function test_check_in_without_an_active_membership_is_rejected(): void
    {
        $f = $this->fixture();

        $this->expectException(ValidationException::class);
        $this->workflows->checkIn($f['member'], 'manual', $f['request']);
    }

    // ---------------------------------------------------------------------
    // checkIn: session limits
    // ---------------------------------------------------------------------

    public function test_check_in_increments_sessions_used_when_limit_is_set(): void
    {
        $f = $this->fixture(['session_limit' => 3]);
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $this->assertSame(0, $membership->refresh()->sessions_used);

        $this->workflows->checkIn($f['member'], 'manual', $f['request']);

        $this->assertSame(1, $membership->refresh()->sessions_used);
    }

    public function test_check_in_is_rejected_when_session_limit_is_reached(): void
    {
        $f = $this->fixture(['session_limit' => 2]);
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $membership->update(['sessions_used' => 2]);

        $this->expectException(ValidationException::class);
        $this->workflows->checkIn($f['member'], 'manual', $f['request']);
    }

    public function test_check_in_without_session_limit_does_not_track_sessions(): void
    {
        $f = $this->fixture();
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);

        $this->workflows->checkIn($f['member'], 'manual', $f['request']);

        $this->assertSame(0, $membership->refresh()->sessions_used);
    }

    // ---------------------------------------------------------------------
    // checkIn: duplicate window and QR toggle
    // ---------------------------------------------------------------------

    public function test_duplicate_check_in_within_window_is_rejected(): void
    {
        $f = $this->fixture();
        $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $this->workflows->checkIn($f['member'], 'manual', $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->checkIn($f['member'], 'manual', $f['request']);
    }

    public function test_qr_check_in_is_rejected_when_disabled(): void
    {
        $f = $this->fixture();
        $f['gym']->update(['settings_json' => ['qr_checkin_enabled' => false]]);
        $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->checkIn($f['member'], 'qr', $f['request']);
    }

    // ---------------------------------------------------------------------
    // recordPayment / voidPayment
    // ---------------------------------------------------------------------

    public function test_record_payment_increments_paid_amount(): void
    {
        $f = $this->fixture();
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);

        $payment = $this->workflows->recordPayment($membership, 40, 'cash', 'REF-1', $f['request']);

        $this->assertSame('completed', $payment->status);
        $this->assertSame('REF-1', $payment->reference_number);
        $this->assertSame('40.00', (string) $membership->refresh()->paid_amount);
    }

    public function test_overpayment_is_rejected(): void
    {
        $f = $this->fixture();
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->recordPayment($membership, 150, 'cash', null, $f['request']);
    }

    public function test_non_positive_payment_is_rejected(): void
    {
        $f = $this->fixture();
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->recordPayment($membership, 0, 'cash', null, $f['request']);
    }

    public function test_void_payment_restores_paid_amount(): void
    {
        $f = $this->fixture();
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $payment = $this->workflows->recordPayment($membership, 40, 'cash', null, $f['request']);

        $this->workflows->voidPayment($payment, 'ثبت اشتباه', $f['request']);

        $this->assertSame('voided', $payment->refresh()->status);
        $this->assertNotNull($payment->voided_at);
        $this->assertSame('ثبت اشتباه', $payment->void_reason);
        $this->assertSame('0.00', (string) $membership->refresh()->paid_amount);
    }

    public function test_voiding_an_already_voided_payment_is_rejected(): void
    {
        $f = $this->fixture();
        $membership = $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $payment = $this->workflows->recordPayment($membership, 40, 'cash', null, $f['request']);
        $this->workflows->voidPayment($payment, 'اول', $f['request']);

        $this->expectException(ValidationException::class);
        $this->workflows->voidPayment($payment->refresh(), 'دوم', $f['request']);
    }

    // ---------------------------------------------------------------------
    // checkOut
    // ---------------------------------------------------------------------

    public function test_double_check_out_is_rejected(): void
    {
        $f = $this->fixture();
        $this->workflows->assignMembership($f['member'], $f['plan'], ['starts_at' => today()->toDateString()], $f['request']);
        $attendance = $this->workflows->checkIn($f['member'], 'manual', $f['request']);

        $this->workflows->checkOut($attendance, $f['request']);
        $this->assertNotNull($attendance->refresh()->checked_out_at);

        $this->expectException(ValidationException::class);
        $this->workflows->checkOut($attendance->refresh(), $f['request']);
    }

    // ---------------------------------------------------------------------
    // createMember
    // ---------------------------------------------------------------------

    public function test_create_member_generates_code_and_normalizes_mobile(): void
    {
        $f = $this->fixture();

        $member = $this->workflows->createMember([
            'branch_id' => $f['branch']->id,
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'mobile' => '۰۹۱۲۳۴۵۶۷۸۹',
            'joined_at' => today()->toDateString(),
            'status' => 'active',
        ], $f['request']);

        $this->assertSame('GYM-00001', $member->membership_code);
        $this->assertSame('09123456789', $member->mobile);
        $this->assertNotNull($member->public_token);
    }

    public function test_create_member_with_plan_assigns_membership_and_initial_payment(): void
    {
        $f = $this->fixture();

        $member = $this->workflows->createMember([
            'branch_id' => $f['branch']->id,
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'mobile' => '09123456789',
            'joined_at' => today()->toDateString(),
            'status' => 'active',
            'membership_plan_id' => $f['plan']->id,
            'initial_payment' => 50,
            'payment_method' => 'cash',
        ], $f['request']);

        $membership = MemberMembership::query()->where('member_id', $member->id)->firstOrFail();
        $this->assertSame($f['plan']->id, $membership->membership_plan_id);
        $this->assertSame('50.00', (string) $membership->paid_amount);
        $this->assertSame(1, Payment::query()->where('membership_id', $membership->id)->count());
    }

    /**
     * @param array<string, mixed> $planOverrides
     * @return array{gym: mixed, member: mixed, plan: mixed, branch: mixed, request: Request}
     */
    private function fixture(array $planOverrides = []): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->setGymContext($gym);

        $branch = $this->createBranch($gym);
        $plan = MembershipPlan::factory()->create(array_merge([
            'branch_id' => $branch->id,
            'duration_days' => 30,
            'price' => 100,
            'is_active' => true,
        ], $planOverrides));
        $member = \App\Models\Member::factory()->create(['branch_id' => $branch->id]);

        $request = Request::create('/test', 'POST');
        $request->setUserResolver(fn () => $owner);

        return ['gym' => $gym, 'member' => $member, 'plan' => $plan, 'branch' => $branch, 'request' => $request];
    }
}
