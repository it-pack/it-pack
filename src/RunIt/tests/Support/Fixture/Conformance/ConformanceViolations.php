<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\Conformance;

use ItPack\RunIt\Conformance\Result\ConformanceResult;

/**
 * Query helpers for asserting on {@see ConformanceResult} in negative tests.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ConformanceViolations
{
    /**
     * @return list<string> violation identifiers in report order
     */
    public static function ids(ConformanceResult $result): array
    {
        return array_values(array_map(static fn ($violation): string => $violation->id, $result->violations));
    }
}
