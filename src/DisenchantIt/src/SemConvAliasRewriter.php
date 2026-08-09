<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use ItPack\DisenchantIt\Contract\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\SemConvConfig;

/**
 * Step 3: rewrites each class's already-placed constant declaration so its
 * value aliases the SemConv interface's constant instead of the raw literal.
 * Operates on the SAME line-based principle as LineBasedRewriter — finds the
 * exact declaration line (by constant name, within the class's brace range)
 * and replaces only that line's value, leaving everything else untouched.
 *
 * This does NOT touch any method body — every `self::PATH` usage inside a
 * class stays completely unchanged; only the `private const string PATH =
 * '...';` declaration line's right-hand side is rewritten to
 * `SemConv::PATH`.
 */
final readonly class SemConvAliasRewriter implements SemConvAliasRewriterInterface
{
    private const string LINEBREAK = SemConv::LINEBREAK;
    private const string CONSTANT_SEPARATOR = SemConv::CONSTANT_SEPARATOR;
    private const string USE_STATEMENT_FORMAT = 'use %s;';
    private const string DECLARATION_PATTERN_REGEX
        = '/^(\s*(?:public|protected|private)?\s*const\s+(?:[A-Za-z_][A-Za-z0-9_]*\s+)?%s\s*=\s*)(.+?)(;\s*)$/';
    private const string NAMESPACE_REGEX = '/^\s*namespace\s+[A-Za-z0-9_\\\\]+;/';
    private const string USE_STATEMENT_REGEX = '/^\s*use\s+[A-Za-z0-9_\\\\]+;/';

    private const int PREG_MATCH_VALUE = 1;

    public function __construct(
        private SemConvConfig $config = new SemConvConfig(),
    ) {
    }

    /**
     * @param string $code
     * @param list<ConstantPlacementInterface> $placements
     * @return string
     */
    public function rewrite(string $code, array $placements): string
    {
        if ($placements === []) {
            return $code;
        }

        $lines = explode(self::LINEBREAK, $code);
        $needsUseImport = false;

        foreach ($placements as $placement) {
            $declarationPattern = sprintf(
                self::DECLARATION_PATTERN_REGEX,
                preg_quote($placement->constantName, '/'),
            );

            foreach ($lines as $index => $line) {
                if (preg_match($declarationPattern, $line, $matches) !== 1) {
                    continue;
                }

                $currentValue = trim($matches[2]);
                $alias = $this->config->interfaceName . self::CONSTANT_SEPARATOR . $placement->constantName;

                if ($currentValue === $alias) {
                    continue; // already aliased — idempotent, don't touch again
                }

                $lines[$index] = $matches[1] . $alias . $matches[3];
                $needsUseImport = true;

                break; // one declaration line per constant name in a class
            }
        }

        if (!$needsUseImport) {
            return implode(self::LINEBREAK, $lines);
        }

        return $this->ensureUseImport(implode(self::LINEBREAK, $lines));
    }

    private function ensureUseImport(string $code): string
    {
        $useStatement = sprintf(
            self::USE_STATEMENT_FORMAT,
            $this->config->fullyQualifiedName(),
        );

        if (str_contains($code, $useStatement)) {
            return $code; // already imported — idempotent
        }

        $lines = explode(self::LINEBREAK, $code);

        // Insert right after the LAST existing `use` statement, or right
        // after `namespace ...;` if there are no `use` statements yet —
        // matches where nette/php-generator and most PHP style guides
        // place imports, without needing to fully parse the use-list.
        $lastUseIndex = null;
        $namespaceIndex = null;

        foreach ($lines as $index => $line) {
            if (preg_match(self::NAMESPACE_REGEX, $line) === self::PREG_MATCH_VALUE) {
                $namespaceIndex = $index;
            }
            if (preg_match(self::USE_STATEMENT_REGEX, $line) === self::PREG_MATCH_VALUE) {
                $lastUseIndex = $index;
            }
        }

        $insertAfter = $lastUseIndex ?? $namespaceIndex;
        if ($insertAfter === null) {
            return $code; // no namespace/use anchor found — leave untouched rather than guess
        }

        array_splice($lines, $insertAfter + 1, 0, [$useStatement]);

        return implode(self::LINEBREAK, $lines);
    }
}
