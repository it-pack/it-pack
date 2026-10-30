<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use LogicException;

/**
 * Shared context-storage behaviour for coroutine/fiber-aware storages.
 *
 * Provides {@see ContextStorageInterface::current()}, `attach()`, and
 * `detach()` using a per-Fiber (or per-coroutine) {@see FiberScopeSlot}.
 * The only abstract dependency is {@see slot()} — which resolves the active
 * slot — so each adapter supplies its own scope-keying strategy while reusing
 * the identical LIFO token protocol Assertions A requires.
 *
 * The trait carries `$counter` and `$fallback` state; consumers must also
 * own a `FiberScopeSlotFactoryInterface`. This mirrors the existing
 * {@see \ItPack\AsyncIt\Conformance\Fixture\RuntimeFixtureTrait} convention.
 *
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/asyncit#ContextStorage
 */
trait CoroutineContextStorageTrait
{
    private ?FiberScopeSlot $fallback = null;

    private int $counter = 0;

    public function current(): ?ExecutionScopeInterface
    {
        $slot = $this->slot();

        if ($slot->stack === []) {
            return null;
        }

        $top = $slot->stack[array_key_last($slot->stack)];

        return $slot->scopes[$top] ?? null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $slot = $this->slot();
        $id = ++$this->counter;
        $token = new ContextToken($id);

        $slot->stack[] = $id;
        $slot->scopes[$id] = $scope;

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        $slot = $this->slot();

        if ($slot->stack === []) {
            throw new LogicException('Cannot detach: no context is currently attached.');
        }

        $top = $slot->stack[array_key_last($slot->stack)];
        if ($top !== $token->id()) {
            throw new LogicException('Cannot detach a context token that is not the most recently attached.');
        }

        unset($slot->stack[array_key_last($slot->stack)], $slot->scopes[$token->id()]);
    }

    abstract protected function slotFactory(): FiberScopeSlotFactoryInterface;

    /**
     * Returns the mutable scope slot for the current coroutine/fiber,
     * or the process-level fallback when none is active.
     */
    abstract protected function slot(): FiberScopeSlot;

    protected function fallback(): FiberScopeSlot
    {
        $this->fallback ??= $this->slotFactory()->create();

        return $this->fallback;
    }
}
