<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

/**
 * Port for building a fresh AST visitor per scan. A new visitor each time keeps
 * per-scan collected state isolated; the concrete adapter injects the
 * RoleClassifierInterface it needs.
 */
interface MagicStringVisitorFactoryInterface
{
    public function build(RoleClassifierInterface $classifier): MagicStringVisitorInterface;
}
