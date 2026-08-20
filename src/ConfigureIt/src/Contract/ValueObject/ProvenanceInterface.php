<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract\ValueObject;

use ItPack\ConfigureIt\ValueObject\Format;

/**
 * Immutable provenance of a single resolved configuration node.
 *
 * Answers the graph-philosophy questions from AGENTS.md: where a value came
 * from, which adapter loaded it, which schema validated it, which processor
 * modified it, whether it overrode another source, and which source had
 * priority. Provenance powers diagnostics, explainability and graph diffing
 * (Roadmap Phase 6).
 *
 * @see docs/architecture/GRAPH.md "Provenance"
 */
interface ProvenanceInterface
{
    public function resourceUri(): string;

    public function format(): Format;

    public function adapter(): string;

    public function priority(): int;

    public function sourceKind(): string;

    /**
     * @return list<string> ordered names of processors that rewrote this node.
     */
    public function processorTrail(): array;

    public function overridden(): bool;

    /**
     * The resource uri this node overrode, when {@see overridden()} is true.
     */
    public function overriddenSource(): ?string;

    /**
     * The schema/stage identifier that validated this node, when known.
     */
    public function validatedBy(): ?string;

    public function at(): float;

    public function withProcessor(string $stageName): self;

    public function asOverridden(?string $shadowedSource = null): self;

    public function validated(string $stageName): self;

    /**
     * @return array<string, mixed> a plain serialisable view (for diagnostics/diff).
     */
    public function toArray(): array;
}
