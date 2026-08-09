<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * Port for detecting magic-string candidates in one PHP source string.
 * The default concrete adapter is nikic/AST based (Detector).
 */
interface StringLiteralDetectorInterface
{
    /**
     * @return list<StringLiteralInterface>
     */
    public function scanSource(string $code): array;

    /**
     * @param list<StringLiteralInterface> $literals
     * @return list<StringLiteralInterface>
     */
    public function filterCandidates(array $literals): array;

    /**
     * @param list<StringLiteralInterface> $candidates
     * @return array<string, list<StringLiteralInterface>>
     */
    public function groupByValue(array $candidates): array;
}
