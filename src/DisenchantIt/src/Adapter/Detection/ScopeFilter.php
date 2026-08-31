<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Detection;

use ItPack\DisenchantIt\Contract\Detection\ScopeFilterInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Scope\DisenchantScopeInterface;

/**
 * Decides whether a literal should be promoted, given the file it came from.
 *
 * Order of checks (first deny wins, then allow-list):
 *   1. `ignoreTests` ⇒ reject files under tests/, fixtures/, var/.
 *   2. excludeLiteralValues ⇒ reject the literal's value outright.
 *   3. excludePaths (glob) ⇒ reject a matching file.
 *   4. includePaths (glob) ⇒ when non-empty, reject files that match none.
 *
 * Namespace rules in the scope are intentionally ignored (see
 * ScopeFilterInterface); they need a namespace source not yet available on the
 * literal carriers.
 */
final readonly class ScopeFilter implements ScopeFilterInterface
{
    /** Conventional directories dropped when `ignoreTests` is set. */
    private const array TEST_DIR_GLOBS = ['tests/**', 'fixtures/**', 'var/**'];

    public function __construct(
        private readonly DisenchantScopeInterface $scope,
    ) {
    }

    public function isAllowed(LiteralInterface $lit, string $filePath): bool
    {
        // 1. Test directories.
        if ($this->scope->isIgnoreTests() && $this->matchesAny(self::TEST_DIR_GLOBS, $filePath)) {
            return false;
        }

        // 2. Explicit literal-value deny-list (file-independent).
        if (
            $this->scope->getExcludeLiteralValues() !== []
            && in_array($lit->value, $this->scope->getExcludeLiteralValues(), true)
        ) {
            return false;
        }

        // 3. File deny-list.
        if ($this->matchesAny($this->scope->getExcludePaths(), $filePath)) {
            return false;
        }

        // 4. File allowlist (only constrains when non-empty).
        $includePaths = $this->scope->getIncludePaths();
        return !($includePaths !== [] && !$this->matchesAny($includePaths, $filePath));
    }

    /**
     * Whether the opt-in FQCN → `::class` converter is enabled for the active
     * scope (delegated from {@see DisenchantScopeInterface}).
     */
    public function isConvertFqcnEnabled(): bool
    {
        return $this->scope->isConvertFqcnToClassConst();
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(array $patterns, string $filePath): bool
    {
        return array_any($patterns, fn ($pattern) => fnmatch($pattern, $filePath));

    }
}
