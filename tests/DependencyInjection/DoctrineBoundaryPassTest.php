<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Scafera\Database\DependencyInjection\DoctrineBoundaryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DoctrineBoundaryPassTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_boundary_' . uniqid();
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testEntityWithNoDoctrineImportsPasses(): void
    {
        $this->writeEntity('Clean.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Scafera\Database\Mapping\Field;
        final class Clean {
            #[Field\Id]
            private ?int $id = null;
        }
        PHP);

        $this->runPass();
        $this->addToAssertionCount(1);
    }

    public function testEntityWithDoctrineImportFails(): void
    {
        $this->writeEntity('Bad.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Doctrine\ORM\Mapping as ORM;
        final class Bad {}
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/imports Doctrine types/');
        $this->runPass();
    }

    public function testEntityWithShortFormLifecycleCallbackWithoutImportPasses(): void
    {
        // Bare #[PrePersist] without a use Doctrine\ import is dead code —
        // PHP cannot resolve the attribute. The import check catches the import-style usage.
        $this->writeEntity('Lifecycle.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Scafera\Database\Mapping\Field;
        final class Lifecycle {
            #[Field\Id]
            private ?int $id = null;
            #[PrePersist]
            public function onPrePersist(): void {}
        }
        PHP);

        $this->runPass();
        $this->addToAssertionCount(1);
    }

    public function testEntityWithDoctrineImportAndLifecycleCallbackFailsOnce(): void
    {
        // When a Doctrine import exists, only the import violation fires — no duplicate
        $this->writeEntity('Both.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Doctrine\ORM\Mapping as ORM;
        final class Both {
            #[ORM\PrePersist]
            public function onPrePersist(): void {}
        }
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/imports Doctrine types/');
        $this->runPass();
    }

    public function testEntityWithFullyQualifiedLifecycleCallbackFails(): void
    {
        $this->writeEntity('LifecycleFq.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Scafera\Database\Mapping\Field;
        final class LifecycleFq {
            #[Field\Id]
            private ?int $id = null;
            #[\Doctrine\ORM\Mapping\PostUpdate]
            public function onPostUpdate(): void {}
        }
        PHP);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/uses lifecycle callback #\[PostUpdate\]/');
        $this->runPass();
    }

    public function testEntityWithBareWordInCommentPasses(): void
    {
        $this->writeEntity('Commented.php', <<<'PHP'
        <?php
        namespace App\Entity;
        use Scafera\Database\Mapping\Field;
        // PrePersist logic should go in a Service
        final class Commented {
            #[Field\Id]
            private ?int $id = null;
        }
        PHP);

        $this->runPass();
        $this->addToAssertionCount(1);
    }

    private function writeEntity(string $filename, string $contents): void
    {
        file_put_contents($this->tmpDir . '/src/Entity/' . $filename, $contents);
    }

    private function runPass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->tmpDir);

        $pass = new DoctrineBoundaryPass();
        $pass->process($container);
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
