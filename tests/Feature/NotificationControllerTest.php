<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithGym;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithGym;

    public function test_user_lists_only_own_notifications(): void
    {
        $f = $this->fixture('member');
        Notification::query()->create(['user_id' => $f['user']->id, 'type' => 'test', 'title' => 'برای من']);
        Notification::query()->create(['user_id' => $f['other']->id, 'type' => 'test', 'title' => 'برای دیگری']);

        $this->get(route('tenant.notifications.index'))
            ->assertOk()
            ->assertSee('برای من')
            ->assertDontSee('برای دیگری');
    }

    public function test_read_marks_notification_and_redirects_to_target(): void
    {
        $f = $this->fixture('member');
        $notification = Notification::query()->create(['user_id' => $f['user']->id, 'type' => 'test', 'title' => 'تست', 'url' => '/dashboard']);

        $this->get(route('tenant.notifications.read', $notification))->assertRedirect('/dashboard');
        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $f = $this->fixture('member');
        Notification::query()->create(['user_id' => $f['user']->id, 'type' => 'test', 'title' => 'یک']);
        Notification::query()->create(['user_id' => $f['user']->id, 'type' => 'test', 'title' => 'دو']);

        $this->post(route('tenant.notifications.read-all'))->assertRedirect();

        $this->assertSame(0, Notification::query()->whereNull('read_at')->count());
    }

    public function test_cannot_read_another_users_notification(): void
    {
        $f = $this->fixture('member');
        $notification = Notification::query()->create(['user_id' => $f['other']->id, 'type' => 'test', 'title' => 'تست']);

        $this->get(route('tenant.notifications.read', $notification))->assertNotFound();
    }

    public function test_cannot_read_another_gyms_notification(): void
    {
        $f = $this->fixture('member');
        $notification = Notification::query()->create(['user_id' => $f['user']->id, 'type' => 'test', 'title' => 'گیم یک']);

        [$gym2] = $this->createGymWithOwner();
        $this->enableModules($gym2, ['notifications']);
        $this->setGymContext($gym2);
        $this->attachUser($gym2, $f['user'], 'member');
        $this->actAsUserInGym($f['user'], $gym2);

        $this->get(route('tenant.notifications.read', $notification))->assertNotFound();
    }

    /**
     * @return array{gym: mixed, user: User, other: User}
     */
    private function fixture(string $role): array
    {
        [$gym, $owner] = $this->createGymWithOwner();
        $this->enableModules($gym, ['notifications']);
        $this->setGymContext($gym);

        $user = $owner;
        if ($role !== 'owner') {
            $user = User::factory()->create();
            $this->attachUser($gym, $user, $role);
        }

        $other = User::factory()->create();
        $this->attachUser($gym, $other, 'member');

        $this->actAsUserInGym($user, $gym);

        return ['gym' => $gym, 'user' => $user, 'other' => $other];
    }
}
