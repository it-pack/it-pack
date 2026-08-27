<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use Closure;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationGraphRewriterInterface;
use ItPack\ConfigureIt\Contract\ConfigurationNodeInterface;

/**
 * Stateless helper that owns the immutable "rewrite every node" graph loop.
 *
 * Extracted from the former {@see AbstractNodeRewritingProcessor} so that
 * node-rewriting processors compose this collaborator instead of extending a
 * shared base (ConfigureIt admits no abstract types). The former base owned
 * the clone-graph → loop-nodes → put sequence plus an optional whole-graph
 * hook; both live here now as a single {@see self::rewrite()} entry point.
 */
final readonly class ConfigurationGraphRewriter implements ConfigurationGraphRewriterInterface
{
    /**
     * Rewrite every node of the graph through `$rewrite`.
     *
     * @param Closure(ConfigurationNodeInterface): ConfigurationNodeInterface $rewrite
     *        applied to each node; must return the (possibly unchanged) node.
     * @param (Closure(ConfigurationGraphInterface): ConfigurationGraphInterface)|null $before
     *        optional hook executed once on the whole graph before the per-node
     *        loop (e.g. validate or inspect the graph); defaults to identity.
     */
    public function rewrite(
        ConfigurationGraphInterface $graph,
        Closure $rewrite,
        ?Closure $before = null,
    ): ConfigurationGraphInterface {
        $graph = null === $before ? $graph : $before($graph);

        $next = new ConfigurationGraph();

        foreach ($graph->nodes() as $node) {
            $next->put($rewrite($node));
        }

        return $next;
    }
}
