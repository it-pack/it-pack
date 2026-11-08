<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Fiber;

use Fiber;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;
use stdClass;
use WeakMap;

/**
 * Per-Fiber execution scope storage.
 *
 * Each Fiber (and the main stack) keeps its own scope stack. A scope attached in one Fiber
 * is invisible to every other Fiber and to the main stack, so concurrent Fibers never share
 * context. Associations are held in a WeakMap keyed by the Fiber object, so once a Fiber is
 * detached and no longer referenced it becomes eligible for collection without manual
 * cleanup. Suspension and resumption preserve the same stack because the current Fiber
 * identity is stable across them.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FiberContextStorage implements ContextStorageInterface
{
    /** @var WeakMap<object, list<FiberContextToken>> $stacks */
    private WeakMap $stacks;

    private readonly object $main;

    public function __construct()
    {
        $this->stacks = new WeakMap();
        $this->main = new stdClass();
    }

    public function current(): ?ExecutionScopeInterface
    {
        $stack = $this->stack();
        $top = end($stack);

        return $top === false ? null : $top->scope;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $anchor = $this->anchor();
        $stack = $this->stacks[$anchor] ?? [];
        $token = new FiberContextToken($anchor, $scope);
        $stack[] = $token;
        $this->stacks[$anchor] = $stack;

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        if (!$token instanceof FiberContextToken || $token->anchor !== $this->anchor()) {
            throw InvalidContextTokenException::mismatched();
        }

        $anchor = $this->anchor();
        $stack = $this->stacks[$anchor] ?? [];
        $top = end($stack);

        if ($top !== $token) {
            throw InvalidContextTokenException::alreadyDetached();
        }

        array_pop($stack);

        if ($stack === []) {
            unset($this->stacks[$anchor]);

            return;
        }

        $this->stacks[$anchor] = $stack;
    }

    private function anchor(): object
    {
        $current = Fiber::getCurrent();

        return $current ?? $this->main;
    }

    /** @return list<FiberContextToken> */
    private function stack(): array
    {
        return $this->stacks[$this->anchor()] ?? [];
    }
}
