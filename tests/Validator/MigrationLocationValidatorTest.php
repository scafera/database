<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Database\Validator\MigrationLocationValidator;

class MigrationLocationValidatorTest extends TestCase
{
    private string $tmpDir;
    private MigrationLocationValidator $validator;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_migloc_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->validator = new MigrationLocationValidator();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testPassesWhenMigrationInMigrationsFolder(): void
    {
        mkdir($this->tmpDir . '/support/migrations', 0777, true);
        file_put_contents($this->tmpDir . '/support/migrations/Version20260101000000.php', <<<'PHP'
        <?php
        use Scafera\Database\Migration;
        final class Version20260101000000 extends Migration {}
        PHP);

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testFailsWhenMigrationAtSupportRoot(): void
    {
        mkdir($this->tmpDir . '/support', 0777, true);
        file_put_contents($this->tmpDir . '/support/Version20260101000000.php', <<<'PHP'
        <?php
        use Scafera\Database\Migration;
        final class Version20260101000000 extends Migration {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('support/Version20260101000000.php', $violations[0]);
        $this->assertStringContainsString('support/migrations/', $violations[0]);
    }

    public function testFailsWhenMigrationInSrc(): void
    {
        mkdir($this->tmpDir . '/src/Migrations', 0777, true);
        file_put_contents($this->tmpDir . '/src/Migrations/Version20260101000000.php', <<<'PHP'
        <?php
        use Scafera\Database\Migration;
        final class Version20260101000000 extends Migration {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('src/Migrations/Version20260101000000.php', $violations[0]);
    }

    public function testSkipsFilesWithoutMigrationBase(): void
    {
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
        file_put_contents($this->tmpDir . '/src/Entity/User.php', '<?php class User {}');

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testPassesWhenProjectHasNoMigrations(): void
    {
        mkdir($this->tmpDir . '/src/Service', 0777, true);
        file_put_contents($this->tmpDir . '/src/Service/DoThing.php', '<?php class DoThing {}');

        $this->assertSame([], $this->validator->validate($this->tmpDir));
    }

    public function testDetectsFqnExtends(): void
    {
        mkdir($this->tmpDir . '/support', 0777, true);
        file_put_contents($this->tmpDir . '/support/Version20260202000000.php', <<<'PHP'
        <?php
        final class Version20260202000000 extends \Scafera\Database\Migration {}
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('support/Version20260202000000.php', $violations[0]);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
