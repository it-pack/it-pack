<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Domain\Exception\InvalidContextTokenException;

/**
 * Process-local, last-in-first-out execution scope storage.
 *
 * Synchronous PHP executions nest on a single call stack, so a plain stack is the correct
 * model: attaching returns a token, and detaching requires that exact token in reverse
 * order. A foreign token, a token from another storage, a token that was already detached,
 * or a token that is not the most recently attached is rejected so the previous scope is
 * always restored correctly.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class StackContextStorage implements ContextStorageInterface
{
    /**
     * @var list<ContextToken>
     */
    private array $stack = [];

    /**
     * @var array<int, true>
     */
    private array $issued = [];

    private int $nextId = 0;

    public function current(): ?ExecutionScopeInterface
    {
        $top = end($this->stack);

        return $top === false ? null : $top->scope;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $token = new ContextToken($this->nextId, $scope);
        $this->nextId++;
        $this->stack[] = $token;
        $this->issued[$token->id] = true;

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        if (!$token instanceof ContextToken || !isset($this->issued[$token->id])) {
            throw InvalidContextTokenException::mismatched();
        }

        $top = end($this->stack);

        if ($top === false || $top !== $token) {
            throw InvalidContextTokenException::alreadyDetached();
        }

        array_pop($this->stack);
        unset($this->issued[$token->id]);
    }
}
