<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

/**
 * No-op observer. Safe default when instrumentation is not required.
 *
 * @api
 */
final class NullRepositoryObserver implements RepositoryObserver
{
    public function onStart(string $operation, array $context = []): void
    {
    }

    public function onSuccess(string $operation, array $context = []): void
    {
    }

    public function onFailure(string $operation, \Throwable $error, array $context = []): void
    {
    }
}
