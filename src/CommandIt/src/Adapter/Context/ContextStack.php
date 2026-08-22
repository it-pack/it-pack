<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Context;

use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Exception\InvalidContextTokenException;
use ItPack\CommandIt\Contract\Port\ContextTokenInterface;
use ItPack\CommandIt\Contract\Port\ExecutionContextProviderInterface;

/**
 * Last-in-first-out stack of entered contexts, keyed by opaque tokens.
 *
 * Implements the {@see ExecutionContextProviderInterface} so a per-Fiber stack is
 * interchangeable with the ambient provider; the only caller that needs the raw
 * stack is the fiber-bound provider that owns it.
 *
 * @internal
 */
final class ContextStack implements ExecutionContextProviderInterface
{
    /**
     * @var list<array{context: CommandContextInterface, token: ContextTokenInterface}>
     */
    private array $items = [];

    public function top(): ?CommandContextInterface
    {
        $top = \end($this->items);

        return $top === false ? null : $top['context'];
    }

    public function current(): ?CommandContextInterface
    {
        return $this->top();
    }

    public function enter(CommandContextInterface $context): ContextTokenInterface
    {
        return $this->push($context);
    }

    public function leave(ContextTokenInterface $token): void
    {
        $this->pop($token);
    }

    public function enrich(\Closure $patch): void
    {
        $index = \array_key_last($this->items);

        if ($index === null) {
            return;
        }

        $this->items[$index]['context'] = $patch($this->items[$index]['context']);
    }

    public function push(CommandContextInterface $context): ContextTokenInterface
    {
        $token = new ContextToken($context);
        $this->items[] = ['context' => $context, 'token' => $token];

        return $token;
    }

    public function pop(ContextTokenInterface $token): void
    {
        $top = \end($this->items);

        if ($top === false || $top['token'] !== $token) {
            throw InvalidContextTokenException::mismatched();
        }

        \array_pop($this->items);
    }
}
