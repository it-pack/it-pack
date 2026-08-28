<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use Closure;

/**
 * Stateless collaborator that owns the immutable "rewrite every node" graph loop.
 *
 * Extracted from the former `AbstractNodeRewritingProcessor` so node-rewriting
 * processors compose this collaborator instead of extending a shared base
 * (ConfigureIt admits no abstract types). The contract lives in `Contract/` so
 * the composition-root wiring can expose the concrete {@see \ItPack\ConfigureIt\Core\ConfigurationGraphRewriter}
 * without `Core` leaking into the contract layer.
 */
interface ConfigurationGraphRewriterInterface
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
    ): ConfigurationGraphInterface;
}
