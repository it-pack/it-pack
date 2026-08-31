<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract\Detection;

/**
 * Port for building a fresh AST visitor per scan. A new visitor each time keeps
 * per-scan collected state isolated; the concrete adapter injects the
 * RoleClassifierInterface it needs and the source code required to recover each
 * literal's exact raw token.
 */
interface MagicStringVisitorFactoryInterface
{
    public function build(RoleClassifierInterface $classifier, string $code): MagicLiteralVisitorInterface;
}
