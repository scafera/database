<?php

declare(strict_types=1);

namespace Scafera\Database\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Scafera\Database\EntityStore;
use Scafera\Database\Mapping\ScaferaMappingDriver;
use Scafera\Database\Tests\Fixtures\TestItem;
use Scafera\Database\Transaction;

class TransactionTest extends TestCase
{
    private EntityManager $em;
    private EntityStore $store;
    private Transaction $tx;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->enableNativeLazyObjects(true);
        $driver = new ScaferaMappingDriver(__DIR__ . '/Fixtures', 'Scafera\\Database\\Tests\\Fixtures');
        $config->setMetadataDriverImpl($driver);

        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->store = new EntityStore($this->em);
        $this->tx = new Transaction($this->em, $this->store);
    }

    public function testRunCommitsEntity(): void
    {
        $this->tx->run(function (): void {
            $this->store->persist(new TestItem('committed'));
        });

        $this->em->clear();
        $found = $this->em->getRepository(TestItem::class)->findOneBy(['name' => 'committed']);
        $this->assertNotNull($found);
        $this->assertSame('committed', $found->getName());
    }

    public function testRunReturnsCallbackValue(): void
    {
        $result = $this->tx->run(fn () => 42);

        $this->assertSame(42, $result);
    }

    public function testRunClearsPendingAfterCommit(): void
    {
        $this->tx->run(function (): void {
            $this->store->persist(new TestItem('cleared'));
        });

        $this->assertFalse($this->store->hasPending());
    }

    public function testExceptionRollsBack(): void
    {
        try {
            $this->tx->run(function (): void {
                $this->store->persist(new TestItem('rolled-back'));
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
        }

        $this->em->clear();
        $found = $this->em->getRepository(TestItem::class)->findOneBy(['name' => 'rolled-back']);
        $this->assertNull($found);
    }

    public function testExceptionClearsPending(): void
    {
        try {
            $this->tx->run(function (): void {
                $this->store->persist(new TestItem('pending'));
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
        }

        $this->assertFalse($this->store->hasPending());
    }

    public function testExceptionIsRethrown(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('original');

        $this->tx->run(function (): void {
            throw new \RuntimeException('original');
        });
    }

    public function testNestedRunCommitsBoth(): void
    {
        $this->tx->run(function (): void {
            $this->store->persist(new TestItem('outer'));

            $this->tx->run(function (): void {
                $this->store->persist(new TestItem('inner'));
            });
        });

        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(TestItem::class)->findOneBy(['name' => 'outer']));
        $this->assertNotNull($this->em->getRepository(TestItem::class)->findOneBy(['name' => 'inner']));
    }

    public function testNestedExceptionBubblesUpToOuter(): void
    {
        // Inner exception propagates — outer transaction rolls back entirely
        $this->expectException(\RuntimeException::class);

        $this->tx->run(function (): void {
            $this->store->persist(new TestItem('outer'));

            $this->tx->run(function (): void {
                throw new \RuntimeException('inner fail');
            });
        });
    }

    public function testNestedExceptionCaughtByOuterStillCommits(): void
    {
        // If outer catches the inner exception, outer can still commit
        $this->tx->run(function (): void {
            $this->store->persist(new TestItem('outer-survives'));

            try {
                $this->tx->run(function (): void {
                    throw new \RuntimeException('inner fail');
                });
            } catch (\RuntimeException) {
            }
        });

        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(TestItem::class)->findOneBy(['name' => 'outer-survives']));
    }

    public function testDepthResetsAfterException(): void
    {
        try {
            $this->tx->run(function (): void {
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
        }

        // Should work as a fresh top-level transaction
        $this->tx->run(function (): void {
            $this->store->persist(new TestItem('after-reset'));
        });

        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(TestItem::class)->findOneBy(['name' => 'after-reset']));
    }

    public function testResetClearsStuckDepth(): void
    {
        // Simulate stuck depth by starting a transaction that won't finish normally
        // We use reflection to set depth > 0 as if a previous run left it corrupted
        $ref = new \ReflectionProperty($this->tx, 'depth');
        $ref->setValue($this->tx, 3);

        $this->tx->reset();

        // After reset, run() should work as a fresh top-level transaction
        $this->tx->run(function (): void {
            $this->store->persist(new TestItem('post-reset'));
        });

        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(TestItem::class)->findOneBy(['name' => 'post-reset']));
    }
}
