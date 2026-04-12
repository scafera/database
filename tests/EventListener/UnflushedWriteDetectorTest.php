<?php

declare(strict_types=1);

namespace Scafera\Database\Tests\EventListener;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Scafera\Database\EntityStore;
use Scafera\Database\EventListener\UnflushedWriteDetector;
use Scafera\Database\Mapping\ScaferaMappingDriver;
use Scafera\Database\Tests\Fixtures\TestItem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\ConsoleEvents;

class UnflushedWriteDetectorTest extends TestCase
{
    private EntityStore $store;
    private UnflushedWriteDetector $detector;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $config = ORMSetup::createConfiguration(isDevMode: true);
        $config->enableNativeLazyObjects(true);
        $driver = new ScaferaMappingDriver(__DIR__ . '/../Fixtures', 'Scafera\\Database\\Tests\\Fixtures');
        $config->setMetadataDriverImpl($driver);

        $em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $this->store = new EntityStore($em);
        $this->detector = new UnflushedWriteDetector($this->store);
    }

    public function testSubscribedEvents(): void
    {
        $events = UnflushedWriteDetector::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertArrayHasKey(ConsoleEvents::TERMINATE, $events);
    }

    public function testOnResponseWithNoPendingDoesNotThrow(): void
    {
        $event = $this->createResponseEvent();

        $this->detector->onResponse($event);
        $this->addToAssertionCount(1);
    }

    public function testOnResponseWithPendingThrows(): void
    {
        $this->store->persist(new TestItem('orphan'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/persist\(\) or remove\(\)/');
        $this->expectExceptionMessageMatches('/TestItem/');

        $this->detector->onResponse($this->createResponseEvent());
    }

    public function testOnConsoleTerminateWithNoPendingDoesNotThrow(): void
    {
        $event = $this->createConsoleEvent();

        $this->detector->onConsoleTerminate($event);
        $this->addToAssertionCount(1);
    }

    public function testOnConsoleTerminateWithPendingThrows(): void
    {
        $this->store->persist(new TestItem('orphan'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/persist\(\) or remove\(\)/');

        $this->detector->onConsoleTerminate($this->createConsoleEvent());
    }

    public function testPendingIsResetAfterThrow(): void
    {
        $this->store->persist(new TestItem('orphan'));

        try {
            $this->detector->onResponse($this->createResponseEvent());
        } catch (\LogicException) {
        }

        $this->assertFalse($this->store->hasPending());
    }

    public function testErrorMessageIncludesOperationCount(): void
    {
        $this->store->persist(new TestItem('one'));
        $this->store->persist(new TestItem('two'));
        $this->store->persist(new TestItem('three'));

        try {
            $this->detector->onResponse($this->createResponseEvent());
            $this->fail('Expected LogicException');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('3 operations', $e->getMessage());
        }
    }

    public function testSubRequestIsIgnored(): void
    {
        $this->store->persist(new TestItem('orphan'));

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, new Request(), HttpKernelInterface::SUB_REQUEST, new Response());

        // Sub-request should not trigger the check
        $this->detector->onResponse($event);
        $this->addToAssertionCount(1);
    }

    private function createResponseEvent(): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, new Response());
    }

    private function createConsoleEvent(): ConsoleTerminateEvent
    {
        $command = new class extends Command {
            protected static $defaultName = 'test:dummy';
        };

        return new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), 0);
    }
}
