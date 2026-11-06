<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Application\CurrentExecutionInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Domain\Exception\NoActiveExecutionException;

/**
 * Ambient accessor for the current execution scope, backed by context storage.
 *
 * It is the explicit, injectable alternative to a mutable static facade: resolution is
 * delegated to the configured {@see ContextStorageInterface}, so the active scope is
 * whatever the storage currently holds (stack, fiber, or coroutine).
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class CurrentExecution implements CurrentExecutionInterface
{
    public function __construct(private ContextStorageInterface $storage)
    {
    }

    public function get(): ?ExecutionScopeInterface
    {
        return $this->storage->current();
    }

    public function require(): ExecutionScopeInterface
    {
        $scope = $this->storage->current();

        if (!$scope instanceof ExecutionScopeInterface) {
            throw NoActiveExecutionException::instance();
        }

        return $scope;
    }
}
