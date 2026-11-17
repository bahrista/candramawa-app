<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests render Inertia pages; they must not depend on `npm run build`
        // having produced public/build/manifest.json (CI does not build assets).
        $this->withoutVite();
    }
}
