<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Context;

use InvalidArgumentException;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use LogicException;

/**
 * Reference context storage implementing the RunIt {@see ContextStorageInterface}
 * contract with a last-in-first-out token stack.
 *
 * The ambient RunIt scope is attached on `attach()` and detached on
 * `detach()` (invoked under a `finally` guard at every suspension point),
 * so a suspended or interleaved task never holds an ambient context —
 * conformance Assertion A.
 *
 * This reference implementation uses a plain array stack rather than a
 * per-fiber `WeakMap<Fiber, ExecutionScope>` slot; it is intended for the
 * synchronous reference runtime where no fibers are active. A per-fiber slot
 * is a later optimisation behind the same contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class MemoryContextStorage implements ContextStorageInterface
{
    /**
     * @var array<int, ContextTokenInterface>
     */
    private array $stack = [];

    /**
     * @var array<int, ExecutionScopeInterface>
     */
    private array $scopes = [];

    private int $counter = 0;

    public function current(): ?ExecutionScopeInterface
    {
        if ($this->stack === []) {
            return null;
        }

        $top = $this->stack[array_key_last($this->stack)];

        return $this->scopes[$this->tokenId($top)];
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $id = ++$this->counter;
        $token = new MemoryContextToken($id);
        $this->stack[] = $token;
        $this->scopes[$id] = $scope;

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        if ($this->stack === []) {
            throw new LogicException('Cannot detach: no context is currently attached.');
        }

        $top = $this->stack[array_key_last($this->stack)];
        if ($top !== $token) {
            throw new LogicException('Cannot detach a context token that is not the most recently attached.');
        }

        $id = $this->tokenId($token);
        unset($this->stack[array_key_last($this->stack)], $this->scopes[$id]);
        $this->stack = array_values($this->stack);
    }

    private function tokenId(ContextTokenInterface $token): int
    {
        if (!$token instanceof MemoryContextToken) {
            throw new InvalidArgumentException('Context token was not issued by this storage.');
        }

        return $token->id;
    }
}
