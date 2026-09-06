<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // .env tiene MAIL_MAILER=smtp con credenciales reales y phpunit.xml no usa
        // force="true", así que dotenv gana: sin esto los tests abren SMTP contra Gmail.
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'test@example.com',
        ]);
    }
}
