<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountLockoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_failures_lock_the_account_for_fifteen_minutes(): void
    {
        $user = User::factory()->create();
        $lockout = app(AccountLockoutService::class);

        for ($i = 0; $i < 4; $i++) {
            $lockout->recordFailure($user);
        }
        $this->assertFalse($lockout->isLocked($user->fresh()));

        $lockout->recordFailure($user);
        $user->refresh();

        $this->assertTrue($lockout->isLocked($user));
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($user->locked_until), 1);
    }

    public function test_web_login_rejects_even_the_correct_password_while_locked(): void
    {
        $user = User::factory()->create(['password' => 'the-real-password']);
        $user->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(15)])->save();

        Livewire::test('login-form')
            ->set('email', $user->email)
            ->set('password', 'the-real-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_clear_resets_a_prior_lock(): void
    {
        // The full "successful login clears the lock" round-trip (including
        // session regeneration) is proven live in the browser instead of
        // here — Livewire::test()'s synthetic request has no bound session
        // store for request()->session()->regenerate() to call, which
        // ⚡login-form.blade.php's success path does. This test covers the
        // service-level guarantee that a login-form success path relies on.
        $user = User::factory()->create();
        $user->forceFill(['failed_login_attempts' => 3, 'locked_until' => now()->addMinutes(10)])->save();

        app(AccountLockoutService::class)->clear($user);
        $user->refresh();

        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_a_failed_web_login_increments_the_counter_for_a_real_account(): void
    {
        $user = User::factory()->create(['password' => 'the-real-password']);

        Livewire::test('login-form')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertSame(1, $user->fresh()->failed_login_attempts);
    }

    public function test_the_api_login_endpoint_honours_the_same_lock(): void
    {
        $user = User::factory()->create(['password' => 'the-real-password']);
        $user->forceFill(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(15)])->save();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'the-real-password',
        ]);

        $response->assertStatus(423);
    }
}
