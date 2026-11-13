<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Runner;

use ItPack\RunIt\Application\Scope\ExecutionScope;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;

/** @internal Scope setup result for runExecution. */
interface ExecutionScopeSetupInterface
{
    public function scope(): ExecutionScope;

    public function execution(): ExecutionInterface;

    public function id(): ExecutionIdInterface;

    public function exportId(): ?string;

    public function scopeAuthority(): mixed;
}
