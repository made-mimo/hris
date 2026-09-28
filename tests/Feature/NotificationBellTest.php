<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notificationFor(User $user): void
    {
        Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'source_module' => 'test',
            'title' => 'Test notification',
            'body' => 'Body',
            'deep_link' => '/',
        ]);
    }

    public function test_badge_shows_the_unread_count(): void
    {
        $user = User::factory()->create();
        $this->notificationFor($user);
        $this->notificationFor($user);

        Livewire::actingAs($user)
            ->test('notification-bell')
            ->assertSee('2')
            ->assertSee('2 unread');
    }

    public function test_badge_caps_at_nine_plus_for_ten_or_more_unread(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->notificationFor($user);
        }

        Livewire::actingAs($user)
            ->test('notification-bell')
            ->assertSee('<span class="count-badge">9+</span>', false)
            ->assertSee('10 unread');
    }

    public function test_badge_disappears_after_mark_all_read(): void
    {
        $user = User::factory()->create();
        $this->notificationFor($user);
        $this->notificationFor($user);

        Livewire::actingAs($user)
            ->test('notification-bell')
            ->assertSee('2 unread')
            ->call('markAllAsRead')
            ->assertDontSee('unread')
            ->assertDontSee('Mark all read');
    }
}
