<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Immutable, last-in-first-out token identifying one attached execution scope.
 *
 * A token is issued by {@see StackContextStorage}. Detachment checks the issuing
 * storage's exact token reference, rather than relying on its integer id alone.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class ContextToken implements ContextTokenInterface
{
    public function __construct(
        public int                     $id,
        public ExecutionScopeInterface $scope,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }
}
