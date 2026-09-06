<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DefaultPortfolioContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_page_carries_the_security_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    /**
     * The public page renders owner-supplied links into hrefs, so the policy
     * that refuses inline script is the one keeping a stored link from
     * becoming a stored execution.
     */
    public function test_the_policy_refuses_inline_and_third_party_script(): void
    {
        $policy = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);

        // Everything this app loads is served from its own origin, so no
        // exception should ever be needed for script.
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
    }

    public function test_the_headers_reach_the_json_endpoints_too(): void
    {
        app(DefaultPortfolioContent::class)->seed();

        $this->getJson('/portfolio')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_the_private_workspace_carries_them(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get($this->adminUrl())
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
