<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Generator;

use ReflectionMethod;

/**
 * Immutable configuration for a derivation run.
 *
 * Defaults reproduce the Phase 1 behavior: public-only surface, static/magic
 * methods dropped, constructors dropped. Every filtering decision is an explicit
 * flag, so behavior is testable and never an accidental opinion.
 *
 * `auditLeakage` (G1) is OFF by default: the deep leakage auditor is stricter
 * than the shallow `assertSelfContained()` (which only forbids `extends`) and
 * would flag existing golden fixtures that intentionally keep an unmapped
 * foreign type; opt in once the derive set / substitution map is complete.
 *
 * The static presets (`strict()`, `forAdapter()`, `forFamily()`) and withers
 * form the fluent builder; `methodRenames` (G11 / B4) is applied uniformly
 * across flat / per-trait / family derivation.
 */
final class DeriveConfig
{
    public const string VISIBILITY_PUBLIC = 'public';
    public const string VISIBILITY_PROTECTED = 'protected';

    /**
     * @param array<string, string> $typeSubstitutionMap Class FQCN => derived interface FQCN.
     * @param list<string> $onlyMethods When non-empty, only these method names are kept.
     * @param list<string> $skipMethods Method names always dropped.
     * @param list<string> $ownedAttributes Attribute class FQCNs permitted when `attributePassThrough` is on.
     * @param array<string, string> $methodRenames Original method name => new name, applied uniformly (G11 / B4).
     */
    public function __construct(
        public readonly string $visibility = self::VISIBILITY_PUBLIC,
        public readonly bool $includeStatic = false,
        public readonly bool $includeMagic = false,
        public readonly bool $dropConstruct = true,
        public readonly bool $dropDestruct = true,
        public readonly array $typeSubstitutionMap = [],
        public readonly bool $substituteSelfStatic = true,
        public readonly bool $includeConstants = false,
        public readonly array $onlyMethods = [],
        public readonly array $skipMethods = [],
        public readonly bool $failOnEmpty = false,
        public readonly bool $attributePassThrough = false,
        public readonly bool $throwsCapture = false,
        public readonly bool $auditLeakage = false,
        public readonly array $ownedAttributes = [],
        public readonly bool $preserveDocblocks = false,
        public readonly array $methodRenames = [],
    ) {
        if (!in_array($visibility, [self::VISIBILITY_PUBLIC, self::VISIBILITY_PROTECTED], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported visibility "%s".', $visibility));
        }

        // EC16: an allow/deny list sharing a name is a contradiction — fail loudly
        // rather than let one silently win.
        $conflict = array_intersect($onlyMethods, $skipMethods);
        if ($conflict !== []) {
            throw new \InvalidArgumentException(sprintf(
                'DeriveConfig contradiction: methods "%s" are both allowed (onlyMethods) and skipped (skipMethods).',
                implode('", "', $conflict),
            ));
        }

        foreach ($methodRenames as $from => $to) {
            if ($from === '') {
                throw new \InvalidArgumentException('DeriveConfig::methodRenames keys must be non-empty method names.');
            }
            if ($to === '') {
                throw new \InvalidArgumentException(sprintf('DeriveConfig::methodRenames target for "%s" must be a non-empty method name.', $from));
            }
        }
    }

    /**
     * Strict-derivation preset: escalate silent drops / leakage. Enables leakage
     * auditing and empty-surface failure, and keeps `self`/`static` rewriting on.
     */
    public static function strict(): self
    {
        return new self(substituteSelfStatic: true, failOnEmpty: true, auditLeakage: true);
    }

    /**
     * Preset tuned for adapter generation: adapters delegate everything the
     * adaptee exposes, so static methods and `self`/`static` rewriting are on.
     */
    public static function forAdapter(): self
    {
        return new self(includeStatic: true, substituteSelfStatic: true);
    }

    /**
     * Preset tuned for family derivation: cross-referencing types must be rewritten
     * to derived interfaces, so `self`/`static` substitution is on.
     */
    public static function forFamily(): self
    {
        return new self(substituteSelfStatic: true);
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

    public function withVisibility(string $visibility): self
    {
        return new self(
            visibility: $visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withIncludeStatic(bool $includeStatic): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withIncludeMagic(bool $includeMagic): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withDropConstruct(bool $dropConstruct): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withDropDestruct(bool $dropDestruct): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withTypeSubstitutionMap(array $typeSubstitutionMap): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withSubstituteSelfStatic(bool $substituteSelfStatic): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withIncludeConstants(bool $includeConstants): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    /**
     * @param list<string> $onlyMethods
     */
    public function withOnlyMethods(array $onlyMethods): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    /**
     * @param list<string> $skipMethods
     */
    public function withSkipMethods(array $skipMethods): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withFailOnEmpty(bool $failOnEmpty): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withAttributePassThrough(bool $attributePassThrough): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withThrowsCapture(bool $throwsCapture): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    public function withAuditLeakage(bool $auditLeakage): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    /**
     * Opt-in: re-emit the source method's phpdoc (`@param` / `@return` /
     * `@throws` / `@template` / `@deprecated`) on the derived method, with FQCN
     * types simplified via the substitution map (G8 / EC9 / EC10). Off by default
     * to keep derived ports terse.
     */
    public function withPreserveDocblocks(bool $preserveDocblocks): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    /**
     * @param list<string> $ownedAttributes Attribute class FQCNs permitted when
     *  `attributePassThrough` is on. An empty list keeps all attributes (current behavior);
     *   a non-empty list keeps only owned attributes.
     */
    public function withOwnedAttributes(array $ownedAttributes): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $this->methodRenames,
        );
    }

    /**
     * Apply a method-rename map uniformly across flat / per-trait / family
     * derivation (G11 / B4). Keyed by the original method name; each value is the new
     * name emitted on the derived interface.
     *
     * @param array<string, string> $methodRenames
     */
    public function withMethodRenames(array $methodRenames): self
    {
        return new self(
            visibility: $this->visibility,
            includeStatic: $this->includeStatic,
            includeMagic: $this->includeMagic,
            dropConstruct: $this->dropConstruct,
            dropDestruct: $this->dropDestruct,
            typeSubstitutionMap: $this->typeSubstitutionMap,
            substituteSelfStatic: $this->substituteSelfStatic,
            includeConstants: $this->includeConstants,
            onlyMethods: $this->onlyMethods,
            skipMethods: $this->skipMethods,
            failOnEmpty: $this->failOnEmpty,
            attributePassThrough: $this->attributePassThrough,
            throwsCapture: $this->throwsCapture,
            auditLeakage: $this->auditLeakage,
            ownedAttributes: $this->ownedAttributes,
            preserveDocblocks: $this->preserveDocblocks,
            methodRenames: $methodRenames,
        );
    }
}
