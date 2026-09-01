<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use Throwable;

/**
 * Immutable observation emitted by the hashers for each hashing operation.
 *
 * Adapters (OpenTelemetry, CloudEvents, ...) implement {@see Contract\HashingObserverInterface}
 * and pair the lifecycle events through the correlation {@see self::$id}.
 */
final readonly class HashObservation
{
    private function __construct(
        public HashObservationKind $kind,
        public string              $id,
        public ?HashOperation      $operation,
        public ?string             $algorithm,
        public ?int                $bytesTotal,
        public ?int                $bytesProcessed,
        public ?int                $bytes,
        public ?int                $durationMs,
        public ?string             $resultUrn,
        public ?Throwable          $error,
    ) {
    }

    public static function started(
        HashOperation $operation,
        string $id,
        string $algorithm,
        ?int $bytesTotal = null,
    ): self {
        return new self(
            HashObservationKind::STARTED,
            $id,
            $operation,
            $algorithm,
            $bytesTotal,
            null,
            null,
            null,
            null,
            null,
        );
    }

    public static function progress(string $id, int $processed, ?int $total = null): self
    {
        return new self(
            HashObservationKind::PROGRESS,
            $id,
            null,
            null,
            $total,
            $processed,
            null,
            null,
            null,
            null
        );
    }

    public static function completed(
        string $id,
        string $algorithm,
        int $bytes,
        int $durationMs,
        ?string $resultUrn = null,
    ): self {
        return new self(
            HashObservationKind::COMPLETED,
            $id,
            null,
            $algorithm,
            null,
            null,
            $bytes,
            $durationMs,
            $resultUrn,
            null,
        );
    }

    public static function failed(string $id, HashOperation $operation, Throwable $error): self
    {
        return new self(
            HashObservationKind::FAILED,
            $id,
            $operation,
            null,
            null,
            null,
            null,
            null,
            null,
            $error
        );
    }
}
