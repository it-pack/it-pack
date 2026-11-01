<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;

final class TraceContextFakeExecutionId implements ExecutionIdInterface
{
    public function __toString(): string
    {
        return 'e1';
    }

    public function toString(): string
    {
        return 'e1';
    }

    public function equals(StringIdentifierInterface $other): bool
    {
        return $other instanceof self && $other->toString() === $this->toString();
    }
}
