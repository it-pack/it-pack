<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Context;

use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Port\ContextTokenInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;

/**
 * Request-local (ambient) command context provider.
 *
 * Holds a single last-in-first-out stack of contexts for the current call
 * stack. Fiber-aware storage wraps this as the fallback for synchronous code.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class AmbientContextStorage implements ExecutionContextProviderInterface
{
    private ContextStack $stack;

    public function __construct()
    {
        $this->stack = new ContextStack();
    }

    public function current(): ?CommandContextInterface
    {
        return $this->stack->current();
    }

    public function enter(CommandContextInterface $context): ContextTokenInterface
    {
        return $this->stack->enter($context);
    }

    public function leave(ContextTokenInterface $token): void
    {
        $this->stack->leave($token);
    }

    public function enrich(\Closure $patch): void
    {
        $this->stack->enrich($patch);
    }
}
