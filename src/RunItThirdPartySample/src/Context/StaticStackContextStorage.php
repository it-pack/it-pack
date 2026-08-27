<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Context;

use InvalidArgumentException;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

/**
 * Plain, synchronous last-in-first-out context storage.
 *
 * It is the simplest possible third-party implementation of
 * {@see ContextStorageInterface}: a single array of tokens. It proves the contract
 * is implementable from scratch against only the port and token interfaces, without
 * leaning on any RunIt internal.
 *
 * Detachment strictly requires the token returned by {@see self::attach()} and rejects
 * any other token (or a non-top token) so a detached or reordered scope is never
 * restored by mistake.
 */
final class StaticStackContextStorage implements ContextStorageInterface
{
    /** @var list<ScopeToken> */
    private array $stack = [];

    public function current(): ?ExecutionScopeInterface
    {
        $top = end($this->stack);

        return $top instanceof ScopeToken ? $top->scope() : null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        $token = new ScopeToken($scope);

        $this->stack[] = $token;

        return $token;
    }

    public function detach(ContextTokenInterface $token): void
    {
        $top = end($this->stack);

        if (!$top instanceof ScopeToken || $top !== $token) {
            throw new InvalidArgumentException(
                'The supplied token does not match the current top of the context stack.',
            );
        }

        array_pop($this->stack);
    }
}
