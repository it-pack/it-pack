<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\CommitIt\Contract\RepositoryObserver;

/**
 * {@see RepositoryObserver} that throws on every dispatch, to prove observer
 * failures never break the wrapped operation (non-recursive path).
 */
final class ThrowingObserver implements RepositoryObserver
{
    public function onStart(string $operation, array $context = []): void
    {
        throw new \RuntimeException('observer onStart failed');
    }

    public function onSuccess(string $operation, array $context = []): void
    {
        throw new \RuntimeException('observer onSuccess failed');
    }

    public function onFailure(string $operation, \Throwable $error, array $context = []): void
    {
        throw new \RuntimeException('observer onFailure failed');
    }
}
