<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Revolt;

use ItPack\AsyncIt\Domain\FiberScopeSlot;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use LogicException;

/**
 * Revolt Fiber-aware {@see ContextStorageInterface}.
 *
 * The ambient RunIt scope is kept in a per-Fiber slot (a {@see \WeakMap} keyed
 * by the active {@see \Fiber}), so concurrent Fibers never observe each other's
 * context (ADR-013). Outside a Fiber (e.g. the conformance harness) it degrades
 * to a single process-level LIFO stack, which still satisfies the token
 * protocol Assertions A requires. Attach/detach is always last-in-first-out and
 * guarded in `finally` by the scheduler.
 *
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-revolt
  * @api

  */
final class RevoltFiberContextStorage implements ContextStorageInterface
{
    /**
     * @var \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlot>
     */
    private \WeakMap $perFiber;

    /**
     * @var FiberScopeSlot|null
     */
    private ?FiberScopeSlot $fallback = null;

    private readonly FiberScopeSlotFactoryInterface $slotFactory;

    private int $counter = 0;

    /**
     * @param \WeakMap<\Fiber<mixed, mixed, mixed, mixed>, FiberScopeSlot> $perFiber
     */
    public function __construct(\WeakMap $perFiber, FiberScopeSlotFactoryInterface $slotFactory)
    {
        $this->perFiber = $perFiber;
        $this->slotFactory = $slotFactory;
    }

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
        $token = new RevoltContextToken($id);

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

    private function slot(): FiberScopeSlot
    {
        /** @var \Fiber<mixed, mixed, mixed, mixed>|null $fiber */
        $fiber = \Fiber::getCurrent();

        if ($fiber === null) {
            return $this->fallback();
        }

        if (!isset($this->perFiber[$fiber])) {
            $this->perFiber[$fiber] = $this->blank();
        }

        return $this->perFiber[$fiber];
    }

    private function fallback(): FiberScopeSlot
    {
        if ($this->fallback === null) {
            $this->fallback = $this->blank();
        }

        return $this->fallback;
    }

    private function blank(): FiberScopeSlot
    {
        return $this->slotFactory->create();
    }
}
