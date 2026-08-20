<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Port for one detected magic-value candidate: a literal value that recurs in
 * source, the constant name it should be promoted to, and every occurrence that
 * would be rewritten.
 */
interface CandidateInterface
{
    public string $value { get; }

    public string $constantName { get; }

    /** @var list<LiteralInterface> */
    public array $occurrences { get; }

    public function count(): int;
}
