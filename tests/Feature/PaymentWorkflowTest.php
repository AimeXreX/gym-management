<?php

namespace Tests\Feature;

use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_manager_can_record_a_payment_on_a_membership(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'payments']);
        $membership = $this->renewAsManager($fixture);

        $this->from('/payments')
            ->post("/memberships/{$membership->id}/payments", ['amount' => 40, 'payment_method' => 'cash'])
            ->assertRedirect('/payments');

        $this->assertDatabaseHas('payments', [
            'membership_id' => $membership->id,
            'amount' => 40,
            'status' => 'completed',
        ]);

        $membership->refresh();
        $this->assertSame('40.00', $membership->paid_amount);
    }

    public function test_payment_exceeding_remaining_balance_is_rejected(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'payments']);
        $membership = $this->renewAsManager($fixture);

        $this->from('/payments')
            ->post("/memberships/{$membership->id}/payments", ['amount' => 150, 'payment_method' => 'cash'])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseMissing('payments', ['membership_id' => $membership->id]);
    }

    public function test_staff_cannot_record_a_payment(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'payments']);
        $membership = $this->renewAsManager($fixture);

        $staff = User::factory()->create();
        $this->attachUser($fixture['gym'], $staff, 'staff');
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->post("/memberships/{$membership->id}/payments", ['amount' => 40, 'payment_method' => 'cash'])
            ->assertForbidden();

        $this->assertDatabaseMissing('payments', ['membership_id' => $membership->id]);
    }

    public function test_manager_can_void_a_payment(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'payments']);
        $membership = $this->renewAsManager($fixture);
        $payment = $this->recordPayment($membership, 40);

        $this->from('/payments')
            ->post("/payments/{$payment->id}/void", ['reason' => 'ثبت اشتباه بود'])
            ->assertRedirect('/payments');

        $payment->refresh();
        $membership->refresh();

        $this->assertSame('voided', $payment->status);
        $this->assertNotNull($payment->voided_at);
        $this->assertSame('ثبت اشتباه بود', $payment->void_reason);
        $this->assertSame('0.00', $membership->paid_amount);
    }

    public function test_voiding_an_already_voided_payment_is_rejected(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'payments']);
        $membership = $this->renewAsManager($fixture);
        $payment = $this->recordPayment($membership, 40);

        $this->post("/payments/{$payment->id}/void", ['reason' => 'اولین باطل‌سازی'])
            ->assertRedirect();

        $this->from('/payments')
            ->post("/payments/{$payment->id}/void", ['reason' => 'باطل‌سازی دوم'])
            ->assertSessionHasErrors('reason');
    }

    public function test_staff_cannot_void_a_payment(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'payments']);
        $membership = $this->renewAsManager($fixture);
        $payment = $this->recordPayment($membership, 40);

        $staff = User::factory()->create();
        $this->attachUser($fixture['gym'], $staff, 'staff');
        $this->actAsUserInGym($staff, $fixture['gym']);

        $this->post("/payments/{$payment->id}/void", ['reason' => 'تلاش غیرمجاز'])
            ->assertForbidden();
    }

    public function test_payments_page_requires_the_module(): void
    {
        $fixture = $this->createCommercialFixture(['members', 'membership_plans', 'memberships', 'payments']);

        $this->actAsUserInGym($fixture['owner'], $fixture['gym']);
        $this->get('/payments')->assertOk();
    }

    private function renewAsManager(array $fixture): MemberMembership
    {
        $manager = User::factory()->create();
        $this->attachUser($fixture['gym'], $manager, 'manager');
        $this->actAsUserInGym($manager, $fixture['gym']);

        $this->from('/members')
            ->post("/members/{$fixture['member']->id}/renew", ['membership_plan_id' => $fixture['plan']->id])
            ->assertRedirect('/members');

        $membership = MemberMembership::query()->where('member_id', $fixture['member']->id)->firstOrFail();

        return $membership;
    }

    private function recordPayment(MemberMembership $membership, int $amount): Payment
    {
        $this->from('/payments')
            ->post("/memberships/{$membership->id}/payments", ['amount' => $amount, 'payment_method' => 'cash'])
            ->assertRedirect('/payments');

        return Payment::query()->where('membership_id', $membership->id)->firstOrFail();
    }
}
