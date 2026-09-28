<?php

namespace Tests\Unit;

use App\Services\PushEndpointValidator;
use Tests\TestCase;

class PushEndpointValidatorTest extends TestCase
{
    public function test_known_push_services_are_allowed(): void
    {
        $this->assertTrue(PushEndpointValidator::isAllowed('https://fcm.googleapis.com/fcm/send/abc123'));
        $this->assertTrue(PushEndpointValidator::isAllowed('https://android.googleapis.com/gcm/send/abc123'));
        $this->assertTrue(PushEndpointValidator::isAllowed('https://updates.push.services.mozilla.com/wpush/v2/abc'));
        $this->assertTrue(PushEndpointValidator::isAllowed('https://web.push.apple.com/abc'));
        $this->assertTrue(PushEndpointValidator::isAllowed('https://some-region.push.services.mozilla.com/abc'));
        $this->assertTrue(PushEndpointValidator::isAllowed('https://xyz.notify.windows.com/abc'));
        $this->assertTrue(PushEndpointValidator::isAllowed('https://xyz.push.apple.com/abc'));
    }

    public function test_arbitrary_and_internal_hosts_are_rejected(): void
    {
        $this->assertFalse(PushEndpointValidator::isAllowed('https://169.254.169.254/latest/meta-data'));
        $this->assertFalse(PushEndpointValidator::isAllowed('http://localhost/admin'));
        $this->assertFalse(PushEndpointValidator::isAllowed('https://evil.example.com/fcm.googleapis.com'));
        $this->assertFalse(PushEndpointValidator::isAllowed('https://notgoogleapis.com/fcm/send/abc'));
        $this->assertFalse(PushEndpointValidator::isAllowed('not-a-url'));
        $this->assertFalse(PushEndpointValidator::isAllowed(''));
    }

    public function test_http_scheme_is_rejected_even_for_a_known_host(): void
    {
        $this->assertFalse(PushEndpointValidator::isAllowed('http://fcm.googleapis.com/fcm/send/abc123'));
    }
}
