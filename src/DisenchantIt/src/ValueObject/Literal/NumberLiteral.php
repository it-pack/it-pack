<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Literal;

use ItPack\DisenchantIt\ValueObject\Source\NamingContextInterface;

final readonly class NumberLiteral implements LiteralInterface
{
    use LiteralValueObjectTrait;

    public function __construct(
        public string $kind,
        public string $value,
        public string $raw,
        public int $line,
        public LiteralRole $role,
        public string $className,
        public string $methodName,
        public ?NamingContextInterface $namingContext = null,
        public LiteralSubkind $subkind = LiteralSubkind::Unknown,
        public int $endLine = 0,
        public bool $isClassnameString = false,
    ) {
    }
}
