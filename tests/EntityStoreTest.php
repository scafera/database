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

class EntityStoreTest extends TestCase
{
    private EntityManager $em;
    private EntityStore $store;

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
    }

    public function testPersistIncrementsPendingCount(): void
    {
        $item = new TestItem('one');
        $this->store->persist($item);

        $this->assertTrue($this->store->hasPending());
        $this->assertSame([TestItem::class => 1], $this->store->getPending());
    }

    public function testMultiplePersistsAccumulate(): void
    {
        $this->store->persist(new TestItem('one'));
        $this->store->persist(new TestItem('two'));

        $this->assertSame([TestItem::class => 2], $this->store->getPending());
    }

    public function testRemoveIncrementsPendingCount(): void
    {
        $item = new TestItem('one');
        $this->store->persist($item);
        $this->em->flush();
        $this->store->resetPending();

        $this->store->remove($item);

        $this->assertTrue($this->store->hasPending());
        $this->assertSame([TestItem::class => 1], $this->store->getPending());
    }

    public function testHasPendingReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse($this->store->hasPending());
    }

    public function testResetPendingClearsTracker(): void
    {
        $this->store->persist(new TestItem('one'));
        $this->store->resetPending();

        $this->assertFalse($this->store->hasPending());
        $this->assertSame([], $this->store->getPending());
    }

    public function testResetClearsPending(): void
    {
        $this->store->persist(new TestItem('one'));
        $this->store->reset();

        $this->assertFalse($this->store->hasPending());
    }

    public function testFindReturnsNullForMissingId(): void
    {
        $this->assertNull($this->store->find(TestItem::class, 999));
    }

    public function testFindReturnsPersistedEntity(): void
    {
        $item = new TestItem('found-me');
        $this->store->persist($item);
        $this->em->flush();

        $this->em->clear();

        $found = $this->store->find(TestItem::class, $item->getId());
        $this->assertInstanceOf(TestItem::class, $found);
        $this->assertSame('found-me', $found->getName());
    }
}
