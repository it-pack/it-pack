<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\CandidateFilterInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * Default candidate filter: skips enum backing values, exception messages and
 * format strings, and keeps only literals that look identifier-shaped.
 */
final readonly class CandidateFilter implements CandidateFilterInterface
{
    public function __construct(
        private readonly RoleClassifierInterface $classifier,
    ) {
    }

    /**
     * @param list<StringLiteralInterface> $literals
     * @return list<StringLiteralInterface>
     */
    public function filterCandidates(array $literals): array
    {
        return array_values(array_filter(
            $literals,
            fn (StringLiteralInterface $lit): bool =>
                // A backed enum case's value (`case PHP = 'php';`) IS
                // already the constant — PHP enums forbid a const and a
                // case sharing a name in the same enum, so promoting one
                // to a *separate* const doesn't just duplicate the value,
                // it fatals ("Cannot redefine class constant").
                $lit->role !== 'enum_backing_value'
                && !in_array($lit->role, ['exception_message', 'format_string'], true)
                && $this->classifier->looksIdentifierLike($lit->value),
        ));
    }
}
