<?php

declare(strict_types=1);

namespace Scafera\Database\EventListener;

use Scafera\Database\EntityStore;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal Detects persist()/remove() calls that were never committed via Transaction::run().
 *
 * Throws in all environments including production. Silent data loss is worse
 * than a failed request — if a write was expected but not committed, the
 * request should fail rather than succeed with missing data.
 */
final class UnflushedWriteDetector implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityStore $store,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -1024],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', -1024],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->check();
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $this->check();
    }

    private function check(): void
    {
        if (!$this->store->hasPending()) {
            return;
        }

        $pending = $this->store->getPending();
        $this->store->resetPending();

        $details = [];
        foreach ($pending as $class => $count) {
            $details[] = sprintf('  - %s (%d operation%s)', $class, $count, $count > 1 ? 's' : '');
        }

        throw new \LogicException(
            "Scafera\\Database: persist() or remove() was called on EntityStore "
            . "but never committed via Transaction::run(). Data was not saved.\n\n"
            . "Unflushed entities:\n" . implode("\n", $details) . "\n\n"
            . "Fix: Wrap your write operations in \$this->tx->run(function () { ... });",
        );
    }
}
