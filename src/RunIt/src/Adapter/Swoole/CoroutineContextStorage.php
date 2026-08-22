<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Swoole;

use ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;

/**
 * Coroutine-local execution scope storage for Swoole/OpenSwoole.
 *
 * Each coroutine keeps its own scope stack, keyed by coroutine id. A scope attached in one
 * coroutine is invisible to every other coroutine and to the main stack, so sequential
 * requests handled by a persistent worker stay isolated. When a stack empties its entry is
 * removed so a later coroutine that reuses the same id starts clean.
 *
 * The Swoole extension is never a hard dependency: the id provider reports "not in a
 * coroutine" when it is absent, and the execution runner always detaches the scope in a
 * finally block, which is what clears coroutine-local state at completion.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class CoroutineContextStorage implements ContextStorageInterface
{
    /** @var array<string, list<CoroutineContextToken>> */
    private array $stacks = [];

    public function __construct(
        private readonly CoroutineIdProviderInterface $coroutines,
    ) {
    }

    private function key(): string
    {
        $cid = $this->coroutines->currentCoroutineId();

        return $cid >= 0 ? 'cid:' . $cid : 'main';
    }

    /**
     * @return list<CoroutineContextToken>
     */
    private function stack(): array
    {
        return $this->stacks[$this->key()] ?? [];
    }

    public function current(): ?ExecutionScopeInterface
    {
        $stack = $this->stack();
        $top = end($stack);

        return $top === false ? null : $top->scope;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $key = $this->key();
        $stack = $this->stacks[$key] ?? [];
        $token = new CoroutineContextToken($this->coroutines->currentCoroutineId(), $scope);
        $stack[] = $token;
        $this->stacks[$key] = $stack;

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        if (!$token instanceof CoroutineContextToken) {
            throw InvalidContextTokenException::mismatched();
        }

        $key = $token->coroutineId >= 0 ? 'cid:' . $token->coroutineId : 'main';

        if ($key !== $this->key()) {
            throw InvalidContextTokenException::mismatched();
        }

        $stack = $this->stacks[$key] ?? [];
        $top = end($stack);

        if ($top !== $token) {
            throw InvalidContextTokenException::alreadyDetached();
        }

        array_pop($stack);

        if ($stack === []) {
            unset($this->stacks[$key]);

            return;
        }

        $this->stacks[$key] = $stack;
    }
}
