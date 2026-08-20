<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Contract;

use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\SourceCodeInterface;

/**
 * Port for detecting magic-value candidates (strings OR numbers) in one PHP
 * source string. Raw detection only — filtering, grouping and naming live
 * behind their own ports. The default concrete adapter is nikic/AST based
 * (Detector).
 *
 * Detection receives a `SourceCode` value object (rather than a bare string) so
 * the scan result can be cached and reused across an operation without
 * re-parsing the same source.
 */
interface LiteralDetectorInterface
{
    /**
     * @return list<LiteralInterface> every literal found, unfiltered —
     *         classification is attached, filtering is the caller's job.
     */
    public function scanSource(SourceCodeInterface $code): array;

    /**
     * Lazy, memory-friendly variant of scanSource: yields each detected literal
     * as it becomes available. The default adapter parses the whole source once
     * (nikic requires a complete AST) and then yields from the cached result, so
     * callers can consume a large result set without holding it all at once.
     *
     * @return \Generator<int, LiteralInterface, void, void>
     */
    public function scanSourceStreaming(SourceCodeInterface $code): \Generator;
}
