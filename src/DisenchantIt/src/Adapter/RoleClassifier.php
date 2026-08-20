<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;

/**
 * Classifies a String_ node's role by looking at its immediate parent node —
 * this is the AST-native replacement for the PoC's quote-counting regex.
 * No string ever needs to be re-parsed for delimiters; the parser already
 * told us exactly where it starts and ends.
 */
final readonly class RoleClassifier implements RoleClassifierInterface
{
    private const array NOISE_FUNCTIONS = ['sprintf', 'printf', 'vsprintf', 'trigger_error'];

    public function classify(Node $node, ?Node $parent): LiteralRole
    {
        // Numbers are magic-value candidates only when a naming hint is later
        // discovered (P2). Their default role is excluded by the filter until
        // then, so detection can record them without promoting them.
        if ($node instanceof LNumber || $node instanceof DNumber) {
            return LiteralRole::NumericExpression;
        }

        if ($parent instanceof Node\Arg) {
            $call = $parent->getAttribute('parent');

            if ($call instanceof Expr\New_) {
                $className = $call->class instanceof Node\Name ? $call->class->toString() : null;

                // Heuristic: constructing anything whose short name ends in
                // "Exception" is almost always a prose message, not a magic
                // value worth a constant. Cheap without a full class-hierarchy
                // resolution; false negatives (a real Exception subclass named
                // oddly) are an acceptable miss for a first pass.
                if ($className !== null && str_ends_with($className, 'Exception')) {
                    return LiteralRole::ExceptionMessage;
                }

                return LiteralRole::ConstructorArg;
            }

            if ($call instanceof Expr\FuncCall && $call->name instanceof Node\Name) {
                if (in_array($call->name->toString(), self::NOISE_FUNCTIONS, true)) {
                    return LiteralRole::FormatString;
                }

                return LiteralRole::FunctionArg;
            }

            // $this->foo('SomeTag', [...]) — e.g. createOccurrence('X', ...).
            // These are frequently the exact "bare string used as a type tag"
            // case DisenchantIt exists to catch (occurrence types, event
            // names), so they get their own role rather than falling through
            // to unclassified.
            if ($call instanceof Expr\MethodCall || $call instanceof Expr\StaticCall) {
                return LiteralRole::MethodCallArg;
            }

            return LiteralRole::CallArg;
        }

        if ($parent instanceof Node\Expr\ArrayItem) {
            // ['origin' => 'defaults'] — $parent->key is the String_ node
            // itself when this string IS the key; when it's the value
            // instead, $parent->key is a *different* node (or null for a
            // list entry). Distinguish the two rather than tagging both
            // "defaults" and "origin" identically.
            if ($parent->key === $node) {
                return LiteralRole::ArrayKeyLiteral;
            }

            return LiteralRole::ArrayValueLiteral;
        }

        if ($parent instanceof Node\Expr\ArrayDimFetch) {
            return LiteralRole::ArrayKey;
        }

        if ($parent instanceof Expr\BinaryOp\Identical || $parent instanceof Expr\BinaryOp\Equal) {
            return LiteralRole::Comparison;
        }

        if ($parent instanceof Node\Expr\Match_ || $parent instanceof Node\Stmt\Case_) {
            return LiteralRole::BranchValue;
        }

        if ($parent instanceof Node\Stmt\EnumCase) {
            return LiteralRole::EnumBackingValue;
        }

        // `private const string PATH = 'path';` — a class constant's OWN
        // declaration value must never be treated as a usage site of that same
        // constant. Excluding it is what makes a rewrite idempotent: on a
        // second pass the const's value literal is no longer re-promoted
        // (which would corrupt it into `self::PATH`).
        if ($parent instanceof Node\Const_) {
            return LiteralRole::ClassConstValue;
        }

        return LiteralRole::Unclassified;
    }

    /**
     * Shape-based pre-filter, independent of a parent role: does this look
     * like an identifier/token rather than prose? Inexpensive early-out before
     * even considering the parent node.
     */
    public function looksIdentifierLike(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        // No internal whitespace runs beyond a single separator character,
        // and no sentence-ending punctuation. 'json', 'admin-user',
        // 'APP_KEY' pass; "Configuration key %s is empty." fails on the
        // first space + trailing period alone.
        return (bool) preg_match('/^[A-Za-z0-9_\-.\/:]+$/', $value)
            && !str_contains($value, ' ');
    }
}
