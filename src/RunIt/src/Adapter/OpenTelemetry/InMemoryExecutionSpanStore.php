<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

use InvalidArgumentException;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;

/**
 * In-memory {@see ExecutionSpanStoreInterface} implementation.
 *
 * Retains up to the configured number of execution and activity span contexts
 * together, evicting the oldest entry when full. Replacing an entry refreshes
 * its age; reading it does not. It retains contexts, not live spans.
 *
 * @see https://opentelemetry.io/
 */
final class InMemoryExecutionSpanStore implements ExecutionSpanStoreInterface
{
    /** @var array<string, SpanContextInterface> */
    private array $contexts = [];

    /** @var array<string, array{previous: ?string, next: ?string}> */
    private array $order = [];

    private ?string $oldest = null;

    private ?string $newest = null;

    public function __construct(private readonly int $capacity = 10000)
    {
        if ($capacity < 1) {
            throw new InvalidArgumentException('Span context capacity must be positive.');
        }
    }

    public function recordExecution(string $executionId, SpanContextInterface $context): void
    {
        $this->record('e:' . $executionId, $context);
    }

    public function recordActivity(string $activityId, SpanContextInterface $context): void
    {
        $this->record('a:' . $activityId, $context);
    }

    public function executionContext(string $executionId): ?SpanContextInterface
    {
        return $this->contexts['e:' . $executionId] ?? null;
    }

    public function activityContext(string $activityId): ?SpanContextInterface
    {
        return $this->contexts['a:' . $activityId] ?? null;
    }

    private function record(string $key, SpanContextInterface $context): void
    {
        if (isset($this->contexts[$key])) {
            $this->remove($key);
        }

        if (count($this->contexts) >= $this->capacity && $this->oldest !== null) {
            $this->remove($this->oldest);
        }

        $this->contexts[$key] = $context;
        $this->order[$key] = ['previous' => $this->newest, 'next' => null];
        if ($this->newest !== null) {
            $this->order[$this->newest]['next'] = $key;
        }

        if ($this->newest === null) {
            $this->oldest = $key;
        }

        $this->newest = $key;
    }

    private function remove(string $key): void
    {
        $previous = $this->order[$key]['previous'];
        $next = $this->order[$key]['next'];
        if ($previous !== null) {
            $this->order[$previous]['next'] = $next;
        }

        if ($previous === null) {
            $this->oldest = $next;
        }

        if ($next !== null) {
            $this->order[$next]['previous'] = $previous;
        }

        if ($next === null) {
            $this->newest = $previous;
        }

        unset($this->contexts[$key], $this->order[$key]);
    }
}
