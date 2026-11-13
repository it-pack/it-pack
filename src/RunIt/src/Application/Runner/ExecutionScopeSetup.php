<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/** @internal Holds the result of execution scope setup for runExecution. */
final readonly class ExecutionScopeSetup implements ExecutionScopeSetupInterface
{
    public function __construct(
        public ExecutionScope $scope,
        public ExecutionInterface $execution,
        public ExecutionIdInterface $id,
        public ?string $exportId,
        public mixed $scopeAuthority,
    ) {
    }

    public function scope(): ExecutionScope
    {
        return $this->scope;
    }

    public function execution(): ExecutionInterface
    {
        return $this->execution;
    }

    public function id(): ExecutionIdInterface
    {
        return $this->id;
    }

    public function exportId(): ?string
    {
        return $this->exportId;
    }

    public function scopeAuthority(): mixed
    {
        return $this->scopeAuthority;
    }
}
