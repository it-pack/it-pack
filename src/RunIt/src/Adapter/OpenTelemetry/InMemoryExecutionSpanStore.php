<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

use InvalidArgumentException;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ExecutionSpanStoreInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
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
    public const int DEFAULT_CAPACITY = 10000;
    public const int MIN_CAPACITY = 1;

    private const string PREFIX_ACTIVITY_ID = 'a:';
    private const string PREFIX_EXECUTION_ID = 'e:';
    private const string KEY_PREVIOUS = 'previous';
    private const string KEY_NEXT = 'next';

    /** @var array<string, SpanContextInterface> */
    private array $contexts = [];

    /** @var array<string, array{previous: ?string, next: ?string}> */
    private array $order = [];

    private ?string $oldest = null;

    private ?string $newest = null;

    public function __construct(private readonly int $capacity = self::DEFAULT_CAPACITY)
    {
        if ($capacity < self::MIN_CAPACITY) {
            throw new InvalidArgumentException('Span context capacity must be positive.');
        }
    }

    public function recordExecution(string|ExecutionIdInterface $executionId, SpanContextInterface $context): void
    {
        $this->record(self::executionKey($executionId), $context);
    }

    public function recordActivity(string|ActivityIdInterface $activityId, SpanContextInterface $context): void
    {
        $this->record(self::activityKey($activityId), $context);
    }

    public function executionContext(string|ExecutionIdInterface $executionId): ?SpanContextInterface
    {
        return $this->contexts[self::executionKey($executionId)] ?? null;
    }

    public function activityContext(string|ActivityIdInterface $activityId): ?SpanContextInterface
    {
        return $this->contexts[self::activityKey($activityId)] ?? null;
    }

    private function record(string $key, SpanContextInterface $context): void
    {
        if (isset($this->contexts[$key])) {
            $this->remove($key);
        }

        if ($this->oldest !== null && count($this->contexts) >= $this->capacity) {
            $this->remove($this->oldest);
        }

        $this->contexts[$key] = $context;
        $this->order[$key] = [
            self::KEY_PREVIOUS => $this->newest,
            self::KEY_NEXT => null
        ];
        if ($this->newest !== null) {
            $this->order[$this->newest][self::KEY_NEXT] = $key;
        }

        if ($this->newest === null) {
            $this->oldest = $key;
        }

        $this->newest = $key;
    }

    private function remove(string $key): void
    {
        $previous = $this->order[$key][self::KEY_PREVIOUS];
        $next = $this->order[$key][self::KEY_NEXT];
        if ($previous !== null) {
            $this->order[$previous][self::KEY_NEXT] = $next;
        }

        if ($previous === null) {
            $this->oldest = $next;
        }

        if ($next !== null) {
            $this->order[$next][self::KEY_PREVIOUS] = $previous;
        }

        if ($next === null) {
            $this->newest = $previous;
        }

        unset($this->contexts[$key], $this->order[$key]);
    }

    private static function activityKey(string|ActivityIdInterface $activityId): string
    {
        return self::PREFIX_ACTIVITY_ID . $activityId;
    }

    private static function executionKey(string|ExecutionIdInterface $executionId): string
    {
        return self::PREFIX_EXECUTION_ID . $executionId;
    }
}
