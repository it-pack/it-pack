<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;

final class MiddlewareFakeCancellationScope implements CancellationScopeInterface
{
    public function isCancelled(): bool
    {
        return false;
    }

    public function cancel(?CancellationReason $reason = null): void
    {
    }

    public function reason(): ?CancellationReason
    {
        return null;
    }

    public function throwIfCancelled(): void
    {
    }

    public function onCancel(callable $callback): void
    {
    }
}
