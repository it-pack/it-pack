<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Contract;

use Closure;
use ItPack\CommandIt\Contract\Domain\CommandContextInterface;

/**
 * Seam for copy-on-write enrichment of the active execution context.
 *
 * Middleware and handlers use this to attach classified (e.g. secret) context
 * that flows to reporters, without owning the stack token. The full
 * {@see \ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface} extends
 * this seam, so consumers can depend on the narrow contract without pulling in
 * the Port layer.
 *
 * @api
 */
interface ContextEnricherInterface
{
    /**
     * Applies a copy-on-write patch to the context currently on top of the stack.
     *
     * The patch receives the current {@see CommandContextInterface} and returns
     * the replacement. The enclosing token is preserved, so the executor's leave
     * still restores the previous context correctly.
     *
     * @param Closure(CommandContextInterface): CommandContextInterface $patch
     */
    public function enrich(Closure $patch): void;
}
