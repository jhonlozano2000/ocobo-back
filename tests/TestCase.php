<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
            'cache.default' => 'array',
            'cache.limiter' => 'array',
            'session.driver' => 'array',
            'broadcasting.default' => 'null',
        ]);

        // La certificación usa la BD de desarrollo, nunca sus archivos ni servicios externos.
        foreach (array_keys(config('filesystems.disks', [])) as $disk) {
            Storage::fake($disk);
        }
        Mail::fake();
        Http::preventStrayRequests();
    }
}
