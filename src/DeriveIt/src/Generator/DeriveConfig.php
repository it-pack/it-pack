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
 *
 * `auditLeakage` (G1) is OFF by default: the deep leakage auditor is stricter
 * than the shallow `assertSelfContained()` (which only forbids `extends`) and
 * would flag existing golden fixtures that intentionally keep an unmapped
 * foreign type; opt in once the derive set / substitution map is complete.
 */
final class DeriveConfig
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PROTECTED = 'protected';

    /**
     * @param array<string, string> $typeSubstitutionMap Class FQCN => derived interface FQCN.
     * @param list<string> $onlyMethods When non-empty, only these method names are kept.
     * @param list<string> $skipMethods Method names always dropped.
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
            ownedAttributes: $this->ownedAttributes,
            auditLeakage: $this->auditLeakage,
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
        );
    }

    /**
     * @param list<string> $ownedAttributes Attribute class FQCNs permitted when
     *                                    `attributePassThrough` is on. An empty list
     *                                    keeps all attributes (current behavior);
     *                                    a non-empty list keeps only owned attributes.
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
        );
    }
}
