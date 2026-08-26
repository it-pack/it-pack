<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;
use ItPack\ConfigureIt\Contract\ConfigurationProcessorInterface;
use ItPack\ConfigureIt\Contract\ProvenanceAwareProcessorInterface;
use ItPack\ConfigureIt\ValueObject\ProcessorStage;

/**
 * Shared skeleton for processors that rewrite every node of the graph.
 *
 * Subclasses implement only {@see rewrite()} for a single node; the base owns
 * the immutable-graph rewrite loop (new graph → loop → put) and the processor
 * stage identity. This removes the clone/loop/put boilerplate that was
 * duplicated across {@see CastingProcessor} and {@see ValidationProcessor}.
 *
 * The {@see before()} hook runs once on the whole graph before the rewrite loop
 * (a no-op by default) so subclasses such as {@see ValidationProcessor} can
 * validate or otherwise inspect the entire graph before rewriting.
 *
 * @see docs/architecture/PIPELINE.md "Processors"
 */
abstract readonly class AbstractNodeRewritingProcessor implements ConfigurationProcessorInterface, ProvenanceAwareProcessorInterface
{
    public function __construct(
        protected ProcessorStage $stage,
    ) {
    }

    public function stageName(): string
    {
        return $this->stage->value;
    }

    public function process(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        $graph = $this->before($graph);

        $next = new ConfigurationGraph();

        foreach ($graph->nodes() as $node) {
            $next->put($this->rewrite($node));
        }

        return $next;
    }

    /**
     * Hook executed once on the whole graph before the per-node rewrite loop.
     *
     * The default is a no-op; override to validate or inspect the graph
     * (e.g. {@see ValidationProcessor} throws on invalid input here).
     */
    protected function before(ConfigurationGraphInterface $graph): ConfigurationGraphInterface
    {
        return $graph;
    }

    /**
     * Rewrite a single node. Must return the (possibly unchanged) node.
     */
    abstract protected function rewrite(ConfigurationNodeInterface $node): ConfigurationNodeInterface;
}
