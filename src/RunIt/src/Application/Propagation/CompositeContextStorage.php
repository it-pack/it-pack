<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;
use WeakMap;

/**
 * Runtime-aware selector over synchronous, Fiber, and coroutine storage.
 *
 * The active storage is chosen by the runtime detector on every call, but the token pins
 * the owning storage so detachment always targets the exact sub-storage that issued it.
 * This keeps Fibers, coroutines, and the synchronous stack isolated while presenting a
 * single {@see ContextStorageInterface}.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class CompositeContextStorage implements ContextStorageInterface
{
    /** @var WeakMap<CompositeContextToken, true> */
    private WeakMap $issued;

    public function __construct(
        private readonly ContextStorageInterface         $synchronous,
        private readonly ContextStorageInterface         $fiber,
        private readonly ContextStorageInterface         $coroutine,
        private readonly RuntimeContextDetectorInterface $detector,
    ) {
        $this->issued = new WeakMap();
    }

    /** Keep token ownership local while sharing the configured storages. */
    public function __clone(): void
    {
        $this->issued = new WeakMap();
    }

    public function current(): ?ExecutionScopeInterface
    {
        return $this->active()->current();
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $storage = $this->active();

        $token = new CompositeContextToken($storage, $storage->attach($scope));
        $this->issued[$token] = true;

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        if (!$token instanceof CompositeContextToken || !isset($this->issued[$token])) {
            throw InvalidContextTokenException::mismatched();
        }

        $token->owner->detach($token->inner);
        unset($this->issued[$token]);
    }

    private function active(): ContextStorageInterface
    {
        if ($this->detector->inCoroutine()) {
            return $this->coroutine;
        }

        if ($this->detector->inFiber()) {
            return $this->fiber;
        }

        return $this->synchronous;
    }
}
