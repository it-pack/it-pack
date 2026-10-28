<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Detection;

use ItPack\DisenchantIt\Contract\Detection\NamingContextExtractorInterface;
use ItPack\DisenchantIt\ValueObject\Source\NamingContext;
use ItPack\DisenchantIt\ValueObject\Source\NamingContextInterface;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * AST-based naming-hint extractor. Given a numeric literal's node it walks up
 * to find the name it was assigned to (variable / property / array-key / param)
 * and any unit suffix that name carries, then scans the source line(s) above
 * for a `//`/`#` comment. The result is a NamingContext the name generator can
 * turn into a constant name without inventing one.
 */
final readonly class NamingContextExtractor implements NamingContextExtractorInterface
{
    /**
     * Unit words safe to treat as a trailing suffix (kept unambiguous to avoid
     * matching ordinary names like "admin" or "maximum").
     *
     * @var list<string>
     */
    private const array UNIT_SUFFIXES = [
        'ms', 'millis',
        'seconds', 'second', 'secs',
        'minutes', 'minute', 'mins',
        'hours', 'hour', 'hrs',
        'days', 'day',
        'bytes', 'byte',
        'kb', 'mb', 'gb', 'tb',
        'ttl', 'hz',
        'bits', 'bit',
        'percent', 'pct',
    ];

    public function extract(Node $node, int $line, string $code): NamingContextInterface
    {
        $variableName = $this->findVariableName($node);
        $unit = $variableName !== null ? $this->detectUnit($variableName) : null;
        $comment = $this->findPrecedingComment($line, $code);

        return new NamingContext($variableName, $comment, $unit);
    }

    /**
     * @return list<Node>
     */
    private function ancestorChain(Node $node): array
    {
        $chain = [];
        $current = $node;
        while ($current !== null) {
            $chain[] = $current;
            $current = $current->getAttribute('parent');
        }

        return $chain;
    }

    private function findVariableName(Node $node): ?string
    {
        $chain = $this->ancestorChain($node);

        $current = $node;
        while ($current !== null) {
            $parent = $current->getAttribute('parent');
            if ($parent === null) {
                break;
            }

            // `$foo = <number>;` — the literal is the assigned value.
            if ($parent instanceof Expr\Assign && in_array($parent->expr, $chain, true)) {
                return $this->nameOf($parent->var);
            }

            // `['key' => <number>]` — the literal is an array value; the key
            // (e.g. a config token) is the hint.
            if ($parent instanceof ArrayItem && in_array($parent->value, $chain, true)) {
                return $parent->key !== null ? $this->nameOf($parent->key) : null;
            }

            // `private int $maxRetries = <number>;` — a typed property default.
            if ($parent instanceof PropertyProperty) {
                return $parent->name->toString();
            }

            // `function f($x = <number>)` — a parameter default.
            if ($parent instanceof Param && $parent->var instanceof Variable && is_string($parent->var->name)) {
                return $parent->var->name;
            }

            $current = $parent;
        }

        return null;
    }

    private function detectUnit(string $name): ?string
    {
        /** @phpstan-ignore-next-line */
        $alternation = implode(
            '|',
            array_map(static fn (string $u): string => preg_quote($u, '/'), self::UNIT_SUFFIXES),
        );
        /** @phpstan-ignore-next-line */
        if (preg_match('/(.*?)(' . $alternation . ')$/i', $name, $m) === 1 && $m[1] !== '') {
            return strtolower($m[2]);
        }

        return null;
    }

    private function findPrecedingComment(int $line, string $code): ?string
    {
        $lines = explode("\n", $code);
        $idx = $line - 2; // 0-based index of the line immediately above $line.

        while ($idx >= 0) {
            $text = trim($lines[$idx]);
            if ($text === '') {
                --$idx;
                continue;
            }

            if (preg_match('/^(?:\/\/|#)\s*(.*)$/', $text, $m) === 1) {
                $comment = trim($m[1]);

                return $comment === '' ? null : $comment;
            }

            // A code line with no comment directly above → no hint comment.
            break;
        }

        return null;
    }

    private function nameOf(Node $node): ?string
    {
        if ($node instanceof Variable && is_string($node->name)) {
            return $node->name;
        }

        if ($node instanceof Identifier) {
            return $node->toString();
        }

        if ($node instanceof String_) {
            return $node->value;
        }

        if ($node instanceof LNumber || $node instanceof DNumber) {
            return (string) $node->value;
        }

        if ($node instanceof Expr\PropertyFetch) {
            return $this->nameOf($node->name);
        }

        if ($node instanceof Expr\ArrayDimFetch) {
            return $this->nameOf($node->var);
        }

        return null;
    }
}
