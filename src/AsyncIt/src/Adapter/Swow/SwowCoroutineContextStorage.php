<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swow;

use ItPack\AsyncIt\Domain\FiberScopeSlot;
use ItPack\AsyncIt\Domain\FiberScopeSlotFactoryInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use LogicException;

/**
 * Swow coroutine-aware {@see ContextStorageInterface}.
 *
 * The ambient RunIt scope is kept in a per-coroutine slot keyed by
 * {@see \Swow\Coroutine::getCid()}, so concurrent Swow coroutines never observe
 * each other's context (ADR-013). Outside a coroutine (e.g. the conformance
 * harness) it degrades to a single process-level LIFO stack, which still
 * satisfies the token protocol Assertions A requires. Attach/detach is always
 * last-in-first-out and guarded in `finally` by the scheduler.
 *
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swow
  * @api

  */
final class SwowCoroutineContextStorage implements ContextStorageInterface
{
    /**
     * @var array<int, FiberScopeSlot>
     */
    private array $perCoroutine = [];

    /**
     * @var FiberScopeSlot|null
     */
    private ?FiberScopeSlot $fallback = null;

    private readonly FiberScopeSlotFactoryInterface $slotFactory;

    private int $counter = 0;

    public function __construct(FiberScopeSlotFactoryInterface $slotFactory)
    {
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
        $token = new SwowContextToken($id);

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
        $cid = $this->coroutineId();

        if ($cid < 0) {
            return $this->fallback();
        }

        if (!isset($this->perCoroutine[$cid])) {
            $this->perCoroutine[$cid] = $this->blank();
        }

        return $this->perCoroutine[$cid];
    }

    private function fallback(): FiberScopeSlot
    {
        if ($this->fallback === null) {
            $this->fallback = $this->blank();
        }

        return $this->fallback;
    }

    private function coroutineId(): int
    {
        if (!class_exists('Swow\\Coroutine')) {
            return -1;
        }

        try {
            $id = \Swow\Coroutine::getCid();
        } catch (\Throwable) {
            return -1;
        }

        return is_int($id) ? $id : -1;
    }

    private function blank(): FiberScopeSlot
    {
        return $this->slotFactory->create();
    }
}
