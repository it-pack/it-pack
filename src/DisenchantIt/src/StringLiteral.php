<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

final class StringLiteral
{
    public function __construct(
        public readonly string $value,
        public readonly int $line,
        /** @var non-empty-string */
        public readonly string $role,
        public readonly string $className,
        public readonly string $methodName,
    ) {
    }
}
