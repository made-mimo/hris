<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePasswordPolicyMet;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SsoLoginTest extends TestCase
{
    use RefreshDatabase;

    private function configureGoogle(bool $enabled = true): void
    {
        Setting::current()->update([
            'sso_google_enabled' => $enabled,
            'sso_google_client_id' => 'test-client-id',
            'sso_google_client_secret' => 'test-client-secret',
        ]);
    }

    public function test_redirect_404s_when_provider_not_configured(): void
    {
        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_redirect_404s_for_an_unknown_provider(): void
    {
        $this->configureGoogle();

        $this->get('/auth/okta/redirect')->assertNotFound();
    }

    public function test_callback_rejects_an_email_with_no_matching_user(): void
    {
        $this->configureGoogle();

        $socialiteUser = (new SocialiteUser)->map(['email' => 'stranger@gmail.com']);
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(
            'No HRIS account matches stranger@gmail.com. Accounts are created by HR during onboarding.',
            session('ssoError')
        );
    }

    public function test_callback_logs_in_a_matching_user_and_satisfies_two_factor(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create(['email' => 'match@systemsintelligenz.com']);

        $socialiteUser = (new SocialiteUser)->map(['email' => 'Match@SystemsIntelligenz.com']);
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(session('two_factor_verified'));
        $this->assertTrue(session('sso_authenticated'));
    }

    public function test_google_domain_restriction_rejects_an_outside_account(): void
    {
        Setting::current()->update([
            'sso_google_enabled' => true,
            'sso_google_client_id' => 'test-client-id',
            'sso_google_client_secret' => 'test-client-secret',
            'sso_google_domain' => 'systemsintelligenz.com',
        ]);
        User::factory()->create(['email' => 'someone@gmail.com']);

        $socialiteUser = (new SocialiteUser)->map(['email' => 'someone@gmail.com']);
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sso_authenticated_session_skips_the_forced_password_change_gate(): void
    {
        $user = User::factory()->create(['password_policy_version' => 0]);
        Setting::current()->update(['password_min_length' => 12]); // bumps policy version past the user's

        $middleware = new EnsurePasswordPolicyMet;
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);
        $request->session()->put('sso_authenticated', true);

        $response = $middleware->handle($request, fn ($req) => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_a_behind_policy_user_without_the_sso_flag_is_still_forced_to_change_password(): void
    {
        $user = User::factory()->create(['password_policy_version' => 0]);
        Setting::current()->update(['password_min_length' => 12]);

        $middleware = new EnsurePasswordPolicyMet;
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn ($req) => new Response('ok'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('update-password', $response->headers->get('Location'));
    }
}
