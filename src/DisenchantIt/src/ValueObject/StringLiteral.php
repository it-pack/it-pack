<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

final readonly class StringLiteral implements StringLiteralInterface
{
    public function __construct(
        public string $value,
        public int $line,
        public string $role,
        public string $className,
        public string $methodName,
    ) {
    }
}
