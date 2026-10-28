<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject\Scope;

/**
 * Immutable configuration describing which files and literals should be opted
 * out of detection.
 *
 * Matching is delegated to the {@see \ItPack\DisenchantIt\Contract\Detection\ScopeFilterInterface}
 * adapter; this object is a pure carrier (replace, never mutate).
 *
 * Directory conventions rejected by `ignoreTests`: `tests/`, `fixtures/`,
 * `var/`.
 */
final readonly class DisenchantScope implements DisenchantScopeInterface
{
    /**
     * @param list<string> $includePaths     glob allow-list; non-empty ⇒ only matching files are analysed
     * @param list<string> $excludePaths     glob deny-list; a match rejects the file
     * @param list<string> $includeNamespaces namespace allow-list (deferred — not yet enforced)
     * @param list<string> $excludeNamespaces namespace deny-list (deferred — not yet enforced)
     * @param list<string> $excludeLiteralValues literal scalar values always rejected
     * @param bool $ignoreTests          drop tests/fixtures/var directories
     * @param int $maxHeredocBodyChars   max length of a heredoc/nowdoc body to collect (0 = unlimited)
     * @param bool $convertFqcnToClassConst enable the opt-in FQCN → `::class` converter
     */
    public function __construct(
        public array $includePaths = [],
        public array $excludePaths = [],
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public array $includeNamespaces = [],
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public array $excludeNamespaces = [],
        public array $excludeLiteralValues = [],
        public bool $ignoreTests = false,
        // @phpstan-ignore shipmonk.deadProperty.neverRead
        public int $maxHeredocBodyChars = self::DEFAULT_MAX_HEREDOC_BODY_CHARS,
        public bool $convertFqcnToClassConst = false,
    ) {
    }

    /**
     * Build a scope from a flat config array (keys optional, all defaulting to
     * empty / false except where noted).
     *
     * @param array<string, mixed> $config
     */
    // @phpstan-ignore shipmonk.deadMethod
    public static function fromConfig(array $config = []): self
    {
        return new self(
            includePaths: self::stringList($config, 'includePaths'),
            excludePaths: self::stringList($config, 'excludePaths'),
            includeNamespaces: self::stringList($config, 'includeNamespaces'),
            excludeNamespaces: self::stringList($config, 'excludeNamespaces'),
            excludeLiteralValues: self::stringList($config, 'excludeLiteralValues'),
            ignoreTests: (bool) ($config['ignoreTests'] ?? false),
            maxHeredocBodyChars: self::intConfig($config, 'maxHeredocBodyChars', self::DEFAULT_MAX_HEREDOC_BODY_CHARS),
            convertFqcnToClassConst: (bool) ($config['convertFqcnToClassConst'] ?? false),
        );
    }

    /**
     * Default scope used by the composition root: opt out of test/fixture/var
     * directories.
     */
    public static function default(): self
    {
        return new self(ignoreTests: true);
    }

    /**
     * Immutable copy with the FQCN → `::class` converter flag toggled. Replaces
     * rather than mutates, per the value-object contract.
     */
    public function withConvertFqcnToClassConst(bool $enabled): self
    {
        return new self(
            includePaths: $this->includePaths,
            excludePaths: $this->excludePaths,
            includeNamespaces: $this->includeNamespaces,
            excludeNamespaces: $this->excludeNamespaces,
            excludeLiteralValues: $this->excludeLiteralValues,
            ignoreTests: $this->ignoreTests,
            maxHeredocBodyChars: $this->maxHeredocBodyChars,
            convertFqcnToClassConst: $enabled,
        );
    }

    /** @return list<string> */
    public function getIncludePaths(): array
    {
        return $this->includePaths;
    }

    /** @return list<string> */
    public function getExcludePaths(): array
    {
        return $this->excludePaths;
    }

    /** @return list<string> */
    // @phpstan-ignore shipmonk.deadMethod
    public function getIncludeNamespaces(): array
    {
        return $this->includeNamespaces;
    }

    /** @return list<string> */
    // @phpstan-ignore shipmonk.deadMethod
    public function getExcludeNamespaces(): array
    {
        return $this->excludeNamespaces;
    }

    /** @return list<string> */
    public function getExcludeLiteralValues(): array
    {
        return $this->excludeLiteralValues;
    }

    public function isIgnoreTests(): bool
    {
        return $this->ignoreTests;
    }

    public function getMaxHeredocBodyChars(): int
    {
        return $this->maxHeredocBodyChars;
    }

    public function isConvertFqcnToClassConst(): bool
    {
        return $this->convertFqcnToClassConst;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private static function stringList(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            $out[] = is_scalar($entry) ? (string) $entry : '';
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function intConfig(array $config, string $key, int $default): int
    {
        $raw = $config[$key] ?? $default;

        if (is_int($raw)) {
            return $raw;
        }

        return is_numeric($raw) ? (int) $raw : $default;
    }
}
