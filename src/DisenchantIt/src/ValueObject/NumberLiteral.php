<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

final readonly class NumberLiteral implements LiteralInterface
{
    use LiteralValueObjectTrait;

    public function __construct(
        public string $kind,
        public string $value,
        public string $raw,
        public int $line,
        public string $role,
        public string $className,
        public string $methodName,
        public ?NamingContextInterface $namingContext = null,
    ) {
    }
}
