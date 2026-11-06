<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use LogicException;
use WeakMap;

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
    private ?FiberScopeSlotInterface $fallback = null;

    private int $counter = 0;

    /** @var WeakMap<ContextTokenInterface, int>|null */
    private ?WeakMap $tokens = null;

    public function current(): ?ExecutionScopeInterface
    {
        $slot = $this->slot();

        $stack = $slot->getStack();

        if ($stack === []) {
            return null;
        }

        $top = array_last($stack);

        $scopes = $slot->getScopes();

        return $scopes[$top] ?? null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $slot = $this->slot();
        $id = ++$this->counter;
        $token = new ContextToken($id);
        $this->tokens ??= new WeakMap();
        $this->tokens[$token] = $id;

        $stack = $slot->getStack();
        $stack[] = $id;
        $slot->setStack($stack);

        $scopes = $slot->getScopes();
        $scopes[$id] = $scope;
        $slot->setScopes($scopes);

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        $slot = $this->slot();

        $stack = $slot->getStack();

        if ($stack === []) {
            throw new LogicException('Cannot detach: no context is currently attached.');
        }

        $top = array_last($stack);
        if (($this->tokens[$token] ?? null) !== $top) {
            throw new LogicException('Cannot detach a context token that is not the most recently attached.');
        }

        unset($stack[array_key_last($stack)]);
        $slot->setStack($stack);

        $scopes = $slot->getScopes();
        unset($scopes[$top], $this->tokens[$token]);
        $slot->setScopes($scopes);
    }

    abstract protected function slotFactory(): FiberScopeSlotFactoryInterface;

    /**
     * Returns the mutable scope slot for the current coroutine/fiber,
     * or the process-level fallback when none is active.
     */
    abstract protected function slot(): FiberScopeSlotInterface;

    protected function fallback(): FiberScopeSlotInterface
    {
        $this->fallback ??= $this->slotFactory()->create();

        return $this->fallback;
    }
}
