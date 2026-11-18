<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Support;

use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ActivityContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use LogicException;

/** Adapts a dedicated execution scope store to the activity context port. */
abstract class ActivityContextStorage implements ActivityContextStorageInterface
{
    /** The underlying storage must not be shared with execution context. */
    public function __construct(private readonly ContextStorageInterface $storage)
    {
    }

    public function current(): ?ActivityScopeInterface
    {
        $scope = $this->storage->current();

        if ($scope instanceof ExecutionScopeInterface && !$scope instanceof ActivityScopeInterface) {
            throw new LogicException('Activity context storage contains a non-activity scope.');
        }

        return $scope;
    }

    public function attach(ActivityScopeInterface $scope): ContextTokenInterface
    {
        return $this->storage->attach($scope);
    }

    public function detach(ContextTokenInterface $token): void
    {
        $this->storage->detach($token);
    }
}
