<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * URL for a path inside the private workspace, built from the configured
     * prefix rather than a literal. phpunit.xml sets ADMIN_PATH to a value
     * that deliberately differs from config/admin.php's default, so a route
     * or fetch URL that hardcodes any prefix fails the suite instead of
     * passing by coincidence.
     */
    protected function adminUrl(string $suffix = ''): string
    {
        return '/'.config('admin.path').$suffix;
    }
}
