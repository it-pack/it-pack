<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for a detected string literal (a magic-value candidate).
 *
 * Declared with PHP 8.4 property hooks so concrete carriers stay slim: a plain
 * `public` property on the implementation satisfies the `{ get; }` contract.
 */
interface StringLiteralInterface
{
    public string $value { get; }

    public int $line { get; }

    public string $role { get; }

    public string $className { get; }

    public string $methodName { get; }
}
