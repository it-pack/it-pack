<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ReflectionMethod;

/**
 * Immutable configuration for a derivation run.
 *
 * Defaults reproduce the Phase 1 behaviour: public-only surface, static/magic
 * methods dropped, constructors dropped. Every filtering decision is an explicit
 * flag so behaviour is testable and never an accidental opinion.
 */
final class DeriveConfig
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PROTECTED = 'protected';

    /**
     * @param array<string, string> $typeSubstitutionMap Class FQCN => derived interface FQCN.
     */
    public function __construct(
        public readonly string $visibility = self::VISIBILITY_PUBLIC,
        public readonly bool $includeStatic = false,
        public readonly bool $includeMagic = false,
        public readonly bool $dropConstruct = true,
        public readonly bool $dropDestruct = true,
        public readonly array $typeSubstitutionMap = [],
    ) {
        if (!in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_PROTECTED], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported visibility "%s".', $visibility));
        }
    }

    /**
     * Bitmask of ReflectionMethod visibility constants allowed for extraction.
     */
    public function visibilityMask(): int
    {
        return $this->visibility === self::VISIBILITY_PROTECTED
            ? ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
            : ReflectionMethod::IS_PUBLIC;
    }

    /**
     * Returns a copy with includeStatic toggled (value object stays immutable).
     */
    public function withIncludeStatic(bool $includeStatic): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
        );
    }
}
