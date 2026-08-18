<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\StringLiteralInterface;

/**
 * Port for detecting magic-string candidates in one PHP source string. Raw
 * detection only — filtering, grouping and naming live behind their own ports
 * (CandidateFilterInterface, CandidateGrouperInterface,
 * ConstantNameGeneratorInterface) so each responsibility is independently
 * swappable and testable.
 *
 * The default concrete adapter is nikic/AST based (Detector).
 */
interface StringLiteralDetectorInterface
{
    /**
     * @return list<StringLiteralInterface>
     */
    public function scanSource(string $code): array;
}
