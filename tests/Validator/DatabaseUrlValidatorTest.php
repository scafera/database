<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Database\Validator\DatabaseUrlValidator;

class DatabaseUrlValidatorTest extends TestCase
{
    private DatabaseUrlValidator $validator;
    private string|false $originalEnv;

    protected function setUp(): void
    {
        $this->validator = new DatabaseUrlValidator();
        $this->originalEnv = getenv('DATABASE_URL');
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv !== false) {
            putenv('DATABASE_URL=' . $this->originalEnv);
            $_ENV['DATABASE_URL'] = $this->originalEnv;
        } else {
            putenv('DATABASE_URL');
            unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);
        }
    }

    public function testPassesWhenDatabaseUrlIsSet(): void
    {
        putenv('DATABASE_URL=mysql://root@localhost/test');

        $this->assertSame([], $this->validator->validate('/tmp'));
    }

    public function testPassesWhenDatabaseUrlInEnvSuperglobal(): void
    {
        putenv('DATABASE_URL');
        $_ENV['DATABASE_URL'] = 'mysql://root@localhost/test';

        $this->assertSame([], $this->validator->validate('/tmp'));
    }

    public function testFailsWhenDatabaseUrlNotDefined(): void
    {
        putenv('DATABASE_URL');
        unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

        $violations = $this->validator->validate('/tmp');
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('DATABASE_URL', $violations[0]);
    }
}
