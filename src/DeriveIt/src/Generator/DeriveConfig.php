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
final readonly class DeriveConfig
{
    public const Visibility VISIBILITY_PUBLIC = Visibility::PUBLIC;
    public const Visibility VISIBILITY_PROTECTED = Visibility::PROTECTED;

    /**
     * @param array<string, string> $typeSubstitutionMap Class FQCN => derived interface FQCN.
     * @param list<string> $onlyMethods When non-empty, only these method names are kept.
     * @param list<string> $skipMethods Method names always dropped.
     * @param list<string> $ownedAttributes Attribute class FQCNs permitted when `attributePassThrough` is on.
     * @param array<string, string> $methodRenames Original method name => new name, applied uniformly (G11 / B4).
     * @param list<string> $reExposeOwnedInterfaces Owned interface FQCNs to re-expose via `extends` (G16 / A4). Opt-in and allow-list scoped; third-party interfaces stay out.
     */
    public readonly Visibility $visibility;

    public function __construct(
        string|Visibility $visibility = self::VISIBILITY_PUBLIC,
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
        public readonly array $reExposeOwnedInterfaces = [],
    ) {
        // Normalize to the enum; an invalid string is rejected as the previous
        // "unsupported visibility" InvalidArgumentException (not a raw ValueError).
        if (is_string($visibility)) {
            try {
                $visibility = Visibility::from($visibility);
            } catch (\ValueError $e) {
                throw new \InvalidArgumentException(sprintf('Unsupported visibility "%s".', $visibility), 0, $e);
            }
        }
        $this->visibility = $visibility;

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

        // G16 / A4: a re-exposed owned interface must be named explicitly; an empty
        // entry would silently drop the `extends` and is almost certainly a mistake.
        foreach ($reExposeOwnedInterfaces as $owned) {
            if ($owned === '') {
                throw new \InvalidArgumentException('DeriveConfig::reExposeOwnedInterfaces must contain non-empty interface FQCNs.');
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
        return $this->visibility === Visibility::PROTECTED
            ? ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
            : ReflectionMethod::IS_PUBLIC;
    }

    /**
     * Returns a copy of this config with the given properties overridden. Reads the
     * current state via `get_object_vars` and re-constructs through the (readonly)
     * constructor, so every wither stays a one-liner and validation is preserved.
     * Unknown keys fail fast — withers address constructor parameters by string
     * key, and a rename would otherwise surface only as a confusing runtime error
     * at the call site instead of at the offending wither (R3-13).
     *
     * @param array<string, mixed> $overrides Property name => new value.
     */
    private function copyWith(array $overrides): self
    {
        $vars = get_object_vars($this);
        foreach ($overrides as $key => $value) {
            if (!array_key_exists($key, $vars)) {
                throw new \LogicException(sprintf(
                    'Unknown DeriveConfig override key "%s" — update the wither to match the constructor parameter.',
                    $key,
                ));
            }
            $vars[$key] = $value;
        }

        return new self(...$vars);
    }

    public function withVisibility(string|Visibility $visibility): self
    {
        $visibility = is_string($visibility) ? Visibility::from($visibility) : $visibility;

        return $this->copyWith(['visibility' => $visibility]);
    }

    public function withIncludeStatic(bool $includeStatic): self
    {
        return $this->copyWith(['includeStatic' => $includeStatic]);
    }

    public function withIncludeMagic(bool $includeMagic): self
    {
        return $this->copyWith(['includeMagic' => $includeMagic]);
    }

    public function withDropConstruct(bool $dropConstruct): self
    {
        return $this->copyWith(['dropConstruct' => $dropConstruct]);
    }

    public function withDropDestruct(bool $dropDestruct): self
    {
        return $this->copyWith(['dropDestruct' => $dropDestruct]);
    }

    public function withTypeSubstitutionMap(array $typeSubstitutionMap): self
    {
        return $this->copyWith(['typeSubstitutionMap' => $typeSubstitutionMap]);
    }

    public function withSubstituteSelfStatic(bool $substituteSelfStatic): self
    {
        return $this->copyWith(['substituteSelfStatic' => $substituteSelfStatic]);
    }

    public function withIncludeConstants(bool $includeConstants): self
    {
        return $this->copyWith(['includeConstants' => $includeConstants]);
    }

    /**
     * @param list<string> $onlyMethods
     */
    public function withOnlyMethods(array $onlyMethods): self
    {
        return $this->copyWith(['onlyMethods' => $onlyMethods]);
    }

    /**
     * @param list<string> $skipMethods
     */
    public function withSkipMethods(array $skipMethods): self
    {
        return $this->copyWith(['skipMethods' => $skipMethods]);
    }

    public function withFailOnEmpty(bool $failOnEmpty): self
    {
        return $this->copyWith(['failOnEmpty' => $failOnEmpty]);
    }

    public function withAttributePassThrough(bool $attributePassThrough): self
    {
        return $this->copyWith(['attributePassThrough' => $attributePassThrough]);
    }

    public function withThrowsCapture(bool $throwsCapture): self
    {
        return $this->copyWith(['throwsCapture' => $throwsCapture]);
    }

    public function withAuditLeakage(bool $auditLeakage): self
    {
        return $this->copyWith(['auditLeakage' => $auditLeakage]);
    }

    /**
     * Opt-in: re-emit the source method's phpdoc (`@param` / `@return` /
     * `@throws` / `@template` / `@deprecated`) on the derived method, with FQCN
     * types simplified via the substitution map (G8 / EC9 / EC10). Off by default
     * to keep derived ports terse.
     */
    public function withPreserveDocblocks(bool $preserveDocblocks): self
    {
        return $this->copyWith(['preserveDocblocks' => $preserveDocblocks]);
    }

    /**
     * @param list<string> $ownedAttributes Attribute class FQCNs permitted when
     *  `attributePassThrough` is on. An empty list keeps all attributes (current behavior);
     *   a non-empty list keeps only owned attributes.
     */
    public function withOwnedAttributes(array $ownedAttributes): self
    {
        return $this->copyWith(['ownedAttributes' => $ownedAttributes]);
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
        return $this->copyWith(['methodRenames' => $methodRenames]);
    }

    /**
     * Re-expose owned interfaces (G16 / A4). Provide the FQCNs of owned interfaces
     * the adaptee implements and that should be carried onto the derived interface
     * via `extends`. Third-party interfaces must NOT be listed — the core leakage
     * invariant holds by default; this opt-in only widens it for interfaces you own.
     *
     * @param list<string> $reExposeOwnedInterfaces
     */
    public function withReExposeOwnedInterfaces(array $reExposeOwnedInterfaces): self
    {
        return $this->copyWith(['reExposeOwnedInterfaces' => $reExposeOwnedInterfaces]);
    }
}
