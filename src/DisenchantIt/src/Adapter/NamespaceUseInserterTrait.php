<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

/**
 * Reusable `use`-statement insertion for line-based generators that emit PHP
 * source without a full AST reprint. Extracted from SemConvAliasRewriter so
 * future generators share one, idempotent implementation.
 *
 * Inserts the `use <fqcn>;` statement right after the LAST existing `use`
 * statement, or right after `namespace ...;` when no `use` exists — without
 * needing to fully parse the use-list. If the statement is already present, or
 * no namespace/use anchor exists, the code is returned unchanged.
 */
trait NamespaceUseInserterTrait
{
    private const string USE_INSERTER_LINEBREAK = "\n";
    private const string USE_STATEMENT_FORMAT = 'use %s;';
    private const string NAMESPACE_REGEX = '/^\s*namespace\s+[A-Za-z0-9_\\\\]+;/';
    private const string USE_STATEMENT_REGEX = '/^\s*use\s+[A-Za-z0-9_\\\\]+;/';

    protected function insertUseStatement(string $code, string $fqcn): string
    {
        $useStatement = sprintf(self::USE_STATEMENT_FORMAT, $fqcn);

        if (str_contains($code, $useStatement)) {
            return $code; // already imported — idempotent
        }

        $lines = explode(self::USE_INSERTER_LINEBREAK, $code);

        // Insert right after the LAST existing `use` statement, or right
        // after `namespace ...;` if there are no `use` statements yet —
        // matches where nette/php-generator and most PHP style guides
        // place imports, without needing to fully parse the use-list.
        $lastUseIndex = null;
        $namespaceIndex = null;

        foreach ($lines as $index => $line) {
            if (preg_match(self::NAMESPACE_REGEX, $line) === 1) {
                $namespaceIndex = $index;
            }
            if (preg_match(self::USE_STATEMENT_REGEX, $line) === 1) {
                $lastUseIndex = $index;
            }
        }

        $insertAfter = $lastUseIndex ?? $namespaceIndex;
        if ($insertAfter === null) {
            return $code; // no namespace/use anchor found — leave untouched rather than guess
        }

        array_splice($lines, $insertAfter + 1, 0, [$useStatement]);

        return implode(self::USE_INSERTER_LINEBREAK, $lines);
    }
}
