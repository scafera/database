<?php

declare(strict_types=1);

namespace Scafera\Database;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class Transaction implements ResetInterface
{
    private int $depth = 0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EntityStore $store,
    ) {
    }

    public function run(callable $operation): mixed
    {
        $this->depth++;

        if ($this->depth === 1) {
            $this->em->getConnection()->beginTransaction();

            try {
                $result = $operation();
                $this->em->flush();
                $this->store->resetPending();
                $this->em->getConnection()->commit();

                return $result;
            } catch (\Throwable $e) {
                $this->em->getConnection()->rollBack();
                $this->store->resetPending();

                throw $e;
            } finally {
                $this->depth--;
            }
        }

        // Nested call — use savepoint for proper rollback semantics
        $savepoint = 'scafera_sp_' . $this->depth;
        $this->em->getConnection()->createSavepoint($savepoint);

        try {
            $result = $operation();

            $this->em->getConnection()->releaseSavepoint($savepoint);

            return $result;
        } catch (\Throwable $e) {
            $this->em->getConnection()->rollbackSavepoint($savepoint);

            throw $e;
        } finally {
            $this->depth--;
        }
    }

    public function reset(): void
    {
        $this->depth = 0;
    }
}
