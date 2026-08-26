<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use RuntimeException;

/**
 * Context storage whose detach() always throws, for exercising restore-failure handling.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ThrowingDetachContextStorage implements ContextStorageInterface
{
    public function current(): ?ExecutionScopeInterface
    {
        return null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        return new FakeContextToken();
    }

    public function detach(ContextTokenInterface $token): void
    {
        throw new RuntimeException('context detach is broken');
    }
}
