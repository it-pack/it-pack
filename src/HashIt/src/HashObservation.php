<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use Throwable;

/**
 * Immutable observation emitted by the hashers for each hashing operation.
 *
 * Adapters (OpenTelemetry, CloudEvents, ...) implement {@see \ItPack\HashIt\Contract\HashingObserverInterface}
 * and pair the lifecycle events through the correlation {@see self::$id}.
 */
final class HashObservation
{
    public const string STARTED = 'started';
    public const string PROGRESS = 'progress';
    public const string COMPLETED = 'completed';
    public const string FAILED = 'failed';

    private function __construct(
        public readonly string $kind,
        public readonly string $id,
        public readonly ?HashOperation $operation,
        public readonly ?string $algorithm,
        public readonly ?int $bytesTotal,
        public readonly ?int $bytesProcessed,
        public readonly ?int $bytes,
        public readonly ?int $durationMs,
        public readonly ?string $resultUrn,
        public readonly ?Throwable $error,
    ) {
    }

    public static function started(HashOperation $operation, string $id, string $algorithm, ?int $bytesTotal = null): self
    {
        return new self(self::STARTED, $id, $operation, $algorithm, $bytesTotal, null, null, null, null, null);
    }

    public static function progress(string $id, int $processed, ?int $total = null): self
    {
        return new self(self::PROGRESS, $id, null, null, $total, $processed, null, null, null, null);
    }

    public static function completed(string $id, string $algorithm, int $bytes, int $durationMs, ?string $resultUrn = null): self
    {
        return new self(self::COMPLETED, $id, null, $algorithm, null, null, $bytes, $durationMs, $resultUrn, null);
    }

    public static function failed(string $id, HashOperation $operation, Throwable $error): self
    {
        return new self(self::FAILED, $id, $operation, null, null, null, null, null, null, $error);
    }
}
