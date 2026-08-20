<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\SemConv;
use ItPack\DisenchantIt\Contract\SemConvAliasRewriterInterface;
use ItPack\DisenchantIt\ValueObject\ConstantPlacementInterface;
use ItPack\DisenchantIt\ValueObject\SemConvConfigInterface;

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
    use NamespaceUseInserterTrait;

    private const string LINEBREAK = SemConv::LINEBREAK;
    private const string CONSTANT_SEPARATOR = SemConv::CONSTANT_SEPARATOR;
    private const string DECLARATION_PATTERN_REGEX
        = '/^(\s*(?:public|protected|private)?\s*const\s+(?:[A-Za-z_][A-Za-z0-9_]*\s+)?%s\s*=\s*)(.+?)(;\s*)$/';

    public function __construct(
        private SemConvConfigInterface $config,
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
        return $this->insertUseStatement($code, $this->config->fullyQualifiedName());
    }
}
