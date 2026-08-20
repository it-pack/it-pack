<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Context;

use ItPack\CommandIt\Contract\Domain\CommandContextInterface;
use ItPack\CommandIt\Contract\Exception\InvalidContextTokenException;
use ItPack\CommandIt\Contract\Port\ContextTokenInterface;

/**
 * Last-in-first-out stack of entered contexts, keyed by opaque tokens.
 *
 * @internal
 */
final class ContextStack
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
