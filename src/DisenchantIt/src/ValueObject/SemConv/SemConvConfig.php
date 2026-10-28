<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\SemConv;

use ItPack\DisenchantIt\Adapter\Grouping\PerClassConstantStrategy;
use ItPack\DisenchantIt\Adapter\Grouping\SameClassConstantStrategy;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvAliasRewriter;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvCollector;
use ItPack\DisenchantIt\Contract\SemConv\SemConv;

/**
 * Step 2 + Step 3 of the SemConv pattern discussed with Tydal:
 *
 *   Step 1 (already exists): SameClassConstantStrategy / PerClassConstantStrategy
 *     turn a repeated literal into `private const string PATH = 'path';` on
 *     whichever class(es) use it.
 *
 *   Step 2 (this file, SemConvCollector): gather ALL placements produced by
 *     Step 1 — across every file in the scan — into one shared, dependency-
 *     free `interface` holding the same constants. Non-destructive: nothing
 *     from Step 1 is touched or removed.
 *
 *   Step 3 (this file, SemConvAliasRewriter): rewrite each class's Step-1
 *     constant so its value POINTS AT the SemConv interface's constant
 *     instead of the raw literal — `private const string PATH =
 *     SemConv::PATH;` — rather than deleting the class-local constant. Every
 *     existing `self::PATH` call site keeps working completely unchanged;
 *     only the constant's own declaration line changes. This is the
 *     "alias-through" layer of separation Tydal asked for: a class still
 *     only ever references its OWN constant, never the shared interface
 *     directly, but that constant is now backed by the shared value rather
 *     than an independent copy of it.
 *
 * Deliberately modeled after OpenTelemetry's SemConv: a name-and-value
 * registry with zero behavior, consumed by reference rather than by
 * duplication. An `interface` (not an enum) is used because these constants
 * are unrelated to each other — not mutually-exclusive variants of one
 * concept the way backed-enum cases are — and interfaces can hold constants
 * without needing any methods.
 */
final class SemConvConfig implements SemConvConfigInterface
{
    private const string DOT = '.';
    private const string PHP_FILE_EXTENSION = 'php';

    private const string NAMESPACE_SEPARATOR = '\\';

    // @phpstan-ignore shipmonk.deadProperty.neverRead
    private readonly ?string $outputPathOverride;

    // @phpstan-ignore shipmonk.deadProperty.neverRead
    public string $outputPath {
        get => $this->outputPathOverride
            ?? $this->interfaceName . self::DOT . self::PHP_FILE_EXTENSION;
    }

    // @phpstan-ignore shipmonk.deadMethod
    public function __construct(
        /**
         * Where the generated interface file is written, relative to the
         * scanned source root. Config, not hardcoded — default matches
         * what was discussed: one shared file for the whole scanned tree,
         * not mirrored per subdirectory. A caller wanting the mirrored
         * layout can pass a different path/namespace per invocation.
         */
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public readonly string $interfaceName = self::DEFAULT_INTERFACE_NAME,
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public readonly ?string $namespace = self::DEFAULT_NAMESPACE,
        ?string $outputPath = null,
    ) {
        $this->outputPathOverride = $outputPath;
    }

    public function fullyQualifiedName(): string
    {
        return $this->namespace === null
            ? $this->interfaceName
            : $this->namespace . self::NAMESPACE_SEPARATOR . $this->interfaceName;
    }
}
