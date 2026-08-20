<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

/**
 * Optional capability for processors that can name their pipeline stage.
 *
 * When a processor implements this interface the resolution pipeline records its
 * {@see stageName()} in the {@see \ItPack\ConfigureIt\Contract\ValueObject\ProvenanceInterface}
 * trail of every node it rewrites, enabling per-node "which processor modified
 * it" diagnostics (Roadmap Phase 6). Processors that do not implement it fall
 * back to their class name.
 *
 * @see docs/architecture/GRAPH.md "Provenance"
 */
interface ProvenanceAwareProcessorInterface
{
    /**
     * A short, stable identifier for this stage (e.g. "VariableProcessor").
     */
    public function stageName(): string;
}
