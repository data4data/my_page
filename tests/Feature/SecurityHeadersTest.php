<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** Pins the Vite hot file, so `npm run dev` cannot change the assertions. */
    private function withDevServer(?string $origin): void
    {
        $path = tempnam(sys_get_temp_dir(), 'vite-hot');

        if ($origin === null) {
            unlink($path);
        } else {
            file_put_contents($path, $origin);
        }

        $this->app->instance(Vite::class, (new Vite)->useHotFile($path));
    }

    private function policyFor(?string $devOrigin): ?string
    {
        $this->withDevServer($devOrigin);

        return $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
    }

    /**
     * CSP has no form for a bracketed IPv6 literal, and a browser drops a source
     * it cannot parse while enforcing the rest — so one bad entry blocks the
     * asset it was meant to allow.
     */
    private function assertEverySourceIsValid(string $policy): void
    {
        $keywords = ["'self'", "'none'", "'unsafe-inline'", "'unsafe-eval'"];

        foreach (explode(';', $policy) as $directive) {
            $sources = preg_split('/\s+/', trim($directive), flags: PREG_SPLIT_NO_EMPTY);
            array_shift($sources); // the directive name

            foreach ($sources as $source) {
                if (in_array($source, $keywords, true) || preg_match('/^[a-z][a-z0-9+.-]*:$/i', $source)) {
                    continue;
                }

                $this->assertMatchesRegularExpression(
                    '#^(?:[a-z][a-z0-9+.-]*://)?(?:\*\.)?[a-z0-9-]+(?:\.[a-z0-9-]+)*(?::[0-9]+|:\*)?(?:/[^\s]*)?$#i',
                    $source,
                    "\"{$source}\" is not a source expression a browser can parse.",
                );
            }
        }
    }

    public function test_the_public_page_carries_the_security_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    // Owner-supplied links land in hrefs, so refusing inline script is what
    // keeps a stored link from becoming a stored execution.
    public function test_the_policy_refuses_inline_and_third_party_script(): void
    {
        $policy = $this->policyFor(null);

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("base-uri 'self'", $policy);
        $this->assertStringContainsString("form-action 'self'", $policy);

        // Everything loads from this origin, so script needs no exception.
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $policy);
        $this->assertStringNotContainsString('unsafe-eval', $policy);
    }

    public function test_the_headers_reach_the_json_endpoints_too(): void
    {
        app(PortfolioSeeder::class)->seed();

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

    public function test_the_shipping_policy_parses(): void
    {
        $this->assertEverySourceIsValid($this->policyFor(null));
    }

    public function test_the_dev_server_origin_is_added_and_still_parses(): void
    {
        $policy = $this->policyFor('http://localhost:5173');

        $this->assertEverySourceIsValid($policy);
        $this->assertStringContainsString('script-src \'self\' http://localhost:5173', $policy);
        $this->assertStringContainsString('ws://localhost:5173', $policy);
        // The dev server serves the CSS and fonts too.
        $this->assertStringContainsString('style-src \'self\' http://localhost:5173', $policy);
        $this->assertStringContainsString('font-src \'self\' http://localhost:5173', $policy);
    }

    // Vite binds to IPv6 loopback by default; vite.config.js pins the host and
    // this is the backstop.
    public function test_no_policy_is_sent_rather_than_one_that_would_block_the_dev_bundle(): void
    {
        $this->assertNull($this->policyFor('http://[::1]:5173'));

        // The headers that do not depend on the dev origin still go out.
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY');
    }
}
