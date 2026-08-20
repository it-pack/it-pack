<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for any detected literal — string or number — that may become a magic
 * value worth promoting to a constant.
 *
 * Declared with PHP 8.4 property hooks so concrete carriers stay slim: a plain
 * `public` property on the implementation satisfies the `{ get; }` contract
 * (the existing StringLiteral pattern proves this).
 *
 * `raw` is the EXACT source token (e.g. `1_000_000`, `0x1F`, `'admin-user'`),
 * distinct from `value` (the parsed scalar as a string). `needle()` is the
 * substring the rewriter searches for on a recorded occurrence line.
 */
interface LiteralInterface
{
    public string $kind { get; }

    public string $value { get; }

    public string $raw { get; }

    public int $line { get; }

    public LiteralRole $role { get; }

    /**
     * Content sub-kind of the literal (URL, regex, SQL, JSON, status code, ...),
     * recognised by ContentClassifier. Defaults to Unknown until classified; it
     * is a pure recognition tag and does not change filtering or rewriting.
     */
    public LiteralSubkind $subkind { get; }

    public string $className { get; }

    public string $methodName { get; }

    /**
     * Naming hints for a numeric literal (unit/comment/variable name), or null
     * for a string literal (strings are named by slug, not by hint). Numbers
     * carry a NamingContext only when a hint was discovered during the scan.
     */
    public ?NamingContextInterface $namingContext { get; }

    public function needle(): string;
}
