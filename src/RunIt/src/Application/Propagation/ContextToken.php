<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Immutable, last-in-first-out token identifying one attached execution scope.
 *
 * A token is issued by {@see StackContextStorage} and carries no behavior; equality with
 * the issuing storage's internal reference is the only operation that matters. This keeps
 * detachment tied to the exact attached scope and prevents restoring the wrong one.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ContextToken implements ContextTokenInterface
{
    public function __construct(
        public readonly int $id,
        public readonly ExecutionScopeInterface $scope,
    ) {
    }
}
