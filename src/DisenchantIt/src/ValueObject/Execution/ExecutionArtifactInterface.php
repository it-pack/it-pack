<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Execution;

use ItPack\DisenchantIt\Contract\Reporting\ExecutionRecorderInterface;

/**
 * Immutable carrier produced by Core during a DisenchantIt run and handed to
 * an ExecutionRecorderInterface. Every artifact is immutable and serializes
 * deterministically through normalize() so a future adapter (OpenTelemetry,
 * CloudEvents, RunIt) can transport it without re-deriving structure.
 *
 * Concrete carriers: DisenchantContext, DisenchantOccurrence,
 * DisenchantObservation, DisenchantMeasurement, DisenchantSnapshot.
 */
interface ExecutionArtifactInterface
{
    /**
     * Stable, lowercase, versioned artifact kind, e.g.
     * "disenchant.it.occurrence.v1". Independent of the PHP class name so the
     * artifact stays addressable across refactors (ADR-0011).
     */
    // @phpstan-ignore shipmonk.deadMethod
    public function kind(): string;

    /** @return array<string, mixed> */
    public function normalize(): array;
}
