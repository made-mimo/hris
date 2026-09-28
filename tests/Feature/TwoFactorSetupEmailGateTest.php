<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TwoFactorSetupEmailGateTest extends TestCase
{
    use RefreshDatabase;

    private function totpOnlyUser(): User
    {
        Setting::current()->update(['two_factor_enabled' => true]);

        $role = Role::create([
            'name' => 'TOTP required role',
            'slug' => 'totp-required-'.uniqid(),
            'two_factor_required' => true,
            'two_factor_allowed_methods' => ['totp'],
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_first_time_totp_setup_requires_an_emailed_code_before_showing_the_qr(): void
    {
        Mail::fake();
        $user = $this->totpOnlyUser();

        $component = Livewire::actingAs($user)->test('two-factor-setup');

        // The single-allowed-method auto-advance in mount() already lands
        // here — no QR/secret has been generated yet.
        $component->assertSet('step', 'totp_email_gate')
            ->assertSet('secret', '')
            ->assertSet('qrDataUri', '');
    }

    public function test_a_wrong_email_code_does_not_reveal_the_qr(): void
    {
        Mail::fake();
        $user = $this->totpOnlyUser();

        Livewire::actingAs($user)->test('two-factor-setup')
            ->set('code', '000000')
            ->call('confirmTotpEmailGate')
            ->assertHasErrors('code')
            ->assertSet('step', 'totp_email_gate')
            ->assertSet('secret', '');
    }

    public function test_the_correct_email_code_reveals_the_qr(): void
    {
        Mail::fake();
        $user = $this->totpOnlyUser();
        $plainCode = $this->capturePlainEmailCode($user);

        Livewire::actingAs($user)->test('two-factor-setup')
            ->set('code', $plainCode)
            ->call('confirmTotpEmailGate')
            ->assertHasNoErrors()
            ->assertSet('step', 'totp')
            ->assertSet('secret', fn ($secret) => $secret !== '');
    }

    private function capturePlainEmailCode(User $user): string
    {
        // sendEmailCode() only persists a hash; mount() already sent one via
        // chooseTotp()'s auto-advance, but its plaintext is unrecoverable,
        // so replace it with a fresh code whose plaintext this test controls.
        $user->emailCodes()->delete();

        $code = (string) random_int(100000, 999999);
        $user->emailCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }
}
