<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Context;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Opaque, identity-only token returned by {@see StaticStackContextStorage::attach()}.
 *
 * The token carries the attached scope so the storage can enforce last-in-first-out
 * detachment of the exact scope that was attached. It exposes no behavior beyond
 * identity, as required by {@see ContextTokenInterface}.
 */
final readonly class ScopeToken implements ContextTokenInterface
{
    public function __construct(
        private ExecutionScopeInterface $scope,
    ) {
    }

    public function scope(): ExecutionScopeInterface
    {
        return $this->scope;
    }
}
