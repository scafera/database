<?php

declare(strict_types=1);

namespace Scafera\Database;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class EntityStore implements ResetInterface
{
    /** @var array<string, int> class name => count of pending operations */
    private array $pending = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function find(string $class, mixed $id): ?object
    {
        return $this->em->find($class, $id);
    }

    public function persist(object $entity): void
    {
        $this->em->persist($entity);
        $class = $entity::class;
        $this->pending[$class] = ($this->pending[$class] ?? 0) + 1;
    }

    public function remove(object $entity): void
    {
        $this->em->remove($entity);
        $class = $entity::class;
        $this->pending[$class] = ($this->pending[$class] ?? 0) + 1;
    }

    public function hasPending(): bool
    {
        return $this->pending !== [];
    }

    /** @return array<string, int> */
    public function getPending(): array
    {
        return $this->pending;
    }

    public function resetPending(): void
    {
        $this->pending = [];
    }

    public function reset(): void
    {
        $this->pending = [];
    }
}
