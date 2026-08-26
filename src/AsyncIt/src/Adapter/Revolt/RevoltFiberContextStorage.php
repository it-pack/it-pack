<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Revolt;

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
 */
final class RevoltFiberContextStorage implements ContextStorageInterface
{
    /**
     * @var \WeakMap<\Fiber, object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}>
     */
    private \WeakMap $perFiber;

    /**
     * @var object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}|null
     */
    private ?object $fallback = null;

    private int $counter = 0;

    public function __construct()
    {
        $this->perFiber = new \WeakMap();
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
        if ($top !== $token->id) {
            throw new LogicException('Cannot detach a context token that is not the most recently attached.');
        }

        unset($slot->stack[array_key_last($slot->stack)], $slot->scopes[$token->id]);
        $slot->stack = array_values($slot->stack);
    }

    /**
     * @return object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}
     */
    private function slot(): object
    {
        $fiber = \Fiber::getCurrent();

        if ($fiber === null) {
            return $this->fallback();
        }

        if (!isset($this->perFiber[$fiber])) {
            $this->perFiber[$fiber] = $this->blank();
        }

        return $this->perFiber[$fiber];
    }

    /**
     * @return object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}
     */
    private function fallback(): object
    {
        if ($this->fallback === null) {
            $this->fallback = $this->blank();
        }

        return $this->fallback;
    }

    /**
     * @return object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}
     */
    private function blank(): object
    {
        return (object) ['stack' => [], 'scopes' => []];
    }
}
