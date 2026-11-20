<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        // Antes de parent: RefreshDatabase ejecutaría migraciones desde setUpTraits().
        if (getenv('DB_DATABASE') === 'ocobo_back') {
            Support\DevelopmentDatabaseGuard::validateTest(static::class);
        }
        parent::setUp();

        config([
            'mail.default' => 'array',
            'mail.from.address' => 'test@example.com',
        ]);
    }
}
