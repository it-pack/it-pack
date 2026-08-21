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
     * The id of the layer this node originated from, when layered composition
     * is in use. Null when no layers were involved (backward compatible).
     */
    public function layerId(): ?string;

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

    /**
     * Whether the underlying value is a secret and must never be rendered in
     * clear text (CLI output, diffs, telemetry, Grafana, …). Drives the central
     * {@see \ItPack\ConfigureIt\Core\RedactingRenderer} mask (M4 — Epic E5.1/E5.2).
     */
    public function isSensitive(): bool;

    public function withProcessor(string $stageName): self;

    public function asOverridden(?string $shadowedSource = null): self;

    public function validated(string $stageName): self;

    /**
     * Returns a copy of this provenance flagged as sensitive (immutable).
     */
    public function asSensitive(): self;

    /**
     * @return array<string, mixed> a plain serialisable view (for diagnostics/diff).
     */
    public function toArray(): array;
}
