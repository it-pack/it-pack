<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\LiteralInterface;

/**
 * Port for detecting magic-value candidates (strings OR numbers) in one PHP
 * source string. Raw detection only — filtering, grouping and naming live
 * behind their own ports. The default concrete adapter is nikic/AST based
 * (Detector).
 */
interface LiteralDetectorInterface
{
    /**
     * @return list<LiteralInterface> every literal found, unfiltered —
     *         classification is attached, filtering is the caller's job.
     */
    public function scanSource(string $code): array;
}
