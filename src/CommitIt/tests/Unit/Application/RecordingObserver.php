<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use ItPack\CommitIt\Contract\RepositoryObserver;

/**
 * {@see RepositoryObserver} that records every dispatched event for assertions.
 *
 * @phpstan-type ObserverEvent array{event: string, operation: string, context: array<string, mixed>, error: ?\Throwable}
 */
final class RecordingObserver implements RepositoryObserver
{
    /** @var list<ObserverEvent> */
    public array $events = [];

    public function onStart(string $operation, array $context = []): void
    {
        $this->events[] = ['event' => 'start', 'operation' => $operation, 'context' => $context, 'error' => null];
    }

    public function onSuccess(string $operation, array $context = []): void
    {
        $this->events[] = ['event' => 'success', 'operation' => $operation, 'context' => $context, 'error' => null];
    }

    public function onFailure(string $operation, \Throwable $error, array $context = []): void
    {
        $this->events[] = ['event' => 'failure', 'operation' => $operation, 'context' => $context, 'error' => $error];
    }

    /**
     * @return list<string>
     */
    public function sequence(): array
    {
        return array_map(static fn (array $event): string => $event['event'] . ':' . $event['operation'], $this->events);
    }
}
