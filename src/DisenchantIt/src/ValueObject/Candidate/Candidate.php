<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Candidate;

use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;

final readonly class Candidate implements CandidateInterface
{
    /**
     * @param list<LiteralInterface> $occurrences every literal occurrence of $value.
     */
    public function __construct(
        public string $value,
        public string $constantName,
        public array $occurrences,
    ) {
    }

    public function count(): int
    {
        return count($this->occurrences);
    }
}
