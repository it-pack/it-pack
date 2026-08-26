<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use RuntimeException;

/**
 * Context storage whose attach() always throws, for exercising attach-failure handling.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ThrowingAttachContextStorage implements ContextStorageInterface
{
    public function current(): ?ExecutionScopeInterface
    {
        return null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        throw new RuntimeException('context attach is broken');
    }

    public function detach(ContextTokenInterface $token): void
    {
    }
}
