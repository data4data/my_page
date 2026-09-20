<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A path inside the workspace, built from the configured prefix. phpunit.xml
     * sets one that differs from the shipped default, so anything hardcoding a
     * prefix fails the suite rather than passing by coincidence.
     */
    protected function adminUrl(string $suffix = ''): string
    {
        return '/'.config('admin.path').$suffix;
    }
}
