<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Database\Validator\AuditableInitValidator;

class AuditableInitValidatorTest extends TestCase
{
    private string $tmpDir;
    private AuditableInitValidator $validator;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_auditable_' . uniqid();
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
        $this->validator = new AuditableInitValidator();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testEntityWithAuditableAndInitPasses(): void
    {
        $this->writeEntity('Order.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Scafera\Database\Mapping\Auditable;
        final class Order {
            use Auditable;
            public function __construct() {
                $this->createdAt = new \DateTimeImmutable();
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    public function testEntityWithAuditableButNoInitFails(): void
    {
        $this->writeEntity('Order.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Scafera\Database\Mapping\Auditable;
        final class Order {
            use Auditable;
            public function __construct() {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('App\\Entity\\Order', $violations[0]);
        $this->assertStringContainsString('createdAt', $violations[0]);
    }

    public function testEntityWithoutAuditablePasses(): void
    {
        $this->writeEntity('Order.php', <<<'PHP'
        <?php
        namespace App\Entity;
        final class Order {
            public function __construct() {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    public function testNoEntityDirectoryPasses(): void
    {
        $this->removeDir($this->tmpDir);
        mkdir($this->tmpDir, 0777, true);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    private function writeEntity(string $filename, string $contents): void
    {
        file_put_contents($this->tmpDir . '/src/Entity/' . $filename, $contents);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
