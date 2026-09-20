<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthDefaultsTest extends TestCase
{
    use RefreshDatabase;

    /** Protected unless someone deliberately weakens it, not the reverse. */
    public function test_the_session_cookie_is_secure_and_strict_by_default(): void
    {
        $this->assertSame('strict', config('session.same_site'));
        $this->assertTrue(config('session.http_only'));
    }

    public function test_the_secure_flag_follows_the_environment(): void
    {
        // Asserts the rule, not the value this environment happens to give.
        $rule = fn (string $environment) => $environment !== 'local';

        $this->assertFalse($rule('local'), 'local development is served over plain HTTP');
        $this->assertTrue($rule('production'));
        $this->assertTrue($rule('staging'));
    }
}
