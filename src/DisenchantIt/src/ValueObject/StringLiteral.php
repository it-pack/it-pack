<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

final readonly class StringLiteral implements StringLiteralInterface
{
    use LiteralValueObjectTrait;

    public function __construct(
        public string $value,
        public int $line,
        public LiteralRole $role,
        public string $className,
        public string $methodName,
        public string $kind = LiteralKind::String->value,
        public string $raw = '',
        public ?NamingContextInterface $namingContext = null,
        public LiteralSubkind $subkind = LiteralSubkind::Unknown,
        public int $endLine = 0,
        public bool $isClassnameString = false,
    ) {
    }
}
