<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Scafera\Database\Validator\RepositoryDisciplineValidator;

class RepositoryDisciplineValidatorTest extends TestCase
{
    private string $tmpDir;
    private RepositoryDisciplineValidator $validator;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/scafera_repo_val_' . uniqid();
        mkdir($this->tmpDir . '/src/Repository', 0777, true);
        $this->validator = new RepositoryDisciplineValidator();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testFlushInCodeIsDetected(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            public function save(): void {
                $this->em->flush();
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('flush()', $violations[0]);
    }

    public function testFlushInLineCommentIsIgnored(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            // We should never call ->flush() here
            public function save(): void {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    public function testFlushInBlockCommentIsIgnored(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            /*
             * Note: ->flush() is handled by Transaction::run()
             */
            public function save(): void {}
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    public function testFlushInStringIsIgnored(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            public function info(): string {
                return "The system will ->flush() on commit";
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    public function testFlushInSingleQuotedStringIsIgnored(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            public function info(): string {
                return 'will ->flush() later';
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    public function testClearInCodeIsDetected(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            public function reset(): void {
                $this->em->clear();
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('clear()', $violations[0]);
    }

    public function testGetConnectionInCodeIsDetected(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            public function raw(): void {
                $conn = $this->em->getConnection();
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(1, $violations);
        $this->assertStringContainsString('raw DBAL connection', $violations[0]);
    }

    public function testCleanRepositoryPasses(): void
    {
        $this->writeRepo('OrderRepository.php', <<<'PHP'
        <?php
        namespace App\Repository;
        class OrderRepository {
            public function findActive(): array {
                return $this->em->createQueryBuilder()
                    ->select('o')
                    ->from('App\Entity\Order', 'o')
                    ->getQuery()
                    ->getResult();
            }
        }
        PHP);

        $violations = $this->validator->validate($this->tmpDir);
        $this->assertCount(0, $violations);
    }

    private function writeRepo(string $filename, string $contents): void
    {
        file_put_contents($this->tmpDir . '/src/Repository/' . $filename, $contents);
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
