<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Candidate;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

/**
 * Port for one detected magic-value candidate: a literal value that recurs in
 * source, the constant name it should be promoted to, and every occurrence that
 * would be rewritten.
 */
interface CandidateInterface
{
    // @phpstan-ignore shipmonk.deadProperty.neverRead
    public string $value { get; }

    // @phpstan-ignore shipmonk.deadProperty.neverRead
    public string $constantName { get; }

    /** @var list<LiteralInterface> */
    public array $occurrences { get; }

    public function count(): int;
}
