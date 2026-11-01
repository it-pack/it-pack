<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;

final readonly class ContextStorageFake implements ContextStorageInterface
{
    public function __construct(private DetachedFlag $flag)
    {
    }

    public function current(): ?ExecutionScopeInterface
    {
        return null;
    }

    public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
    {
        return new ContextTokenFake();
    }

    public function detach(ContextTokenInterface $token): void
    {
        $this->flag->detached = true;
    }
}
