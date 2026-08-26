<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Adapter\Swoole;

use InvalidArgumentException;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use LogicException;

/**
 * Swoole coroutine-aware {@see ContextStorageInterface}.
 *
 * The ambient RunIt scope is kept in a per-coroutine slot keyed by
 * {@see \Swoole\Coroutine::getCid()}, so concurrent Swoole coroutines never
 * observe each other's context (ADR-013). Outside a coroutine (e.g. the
 * conformance harness) it degrades to a single process-level LIFO stack, which
 * still satisfies the token protocol Assertions A requires. Attach/detach is
 * always last-in-first-out and guarded in `finally` by the scheduler.
 *
 * @see https://w3id.org/itpack/runit#Execution
 * @see https://w3id.org/itpack/asyncit/taxonomy#runtime-adapter-swoole
 */
final class SwooleCoroutineContextStorage implements ContextStorageInterface
{
    /**
     * @var array<int, object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}>
     */
    private array $perCoroutine = [];

    /**
     * @var object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}|null
     */
    private ?object $fallback = null;

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
        $token = new SwooleContextToken($id);

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
        $cid = $this->coroutineId();

        if ($cid < 0) {
            return $this->fallback();
        }

        if (!isset($this->perCoroutine[$cid])) {
            $this->perCoroutine[$cid] = $this->blank();
        }

        return $this->perCoroutine[$cid];
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

    private function coroutineId(): int
    {
        if (!class_exists('Swoole\\Coroutine') || !method_exists('Swoole\\Coroutine', 'getCid')) {
            return -1;
        }

        try {
            return \Swoole\Coroutine::getCid();
        } catch (\Throwable) {
            return -1;
        }
    }

    /**
     * @return object{stack: list<int>, scopes: array<int, ExecutionScopeInterface>}
     */
    private function blank(): object
    {
        return (object) ['stack' => [], 'scopes' => []];
    }
}
