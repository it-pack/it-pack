<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\ContentClassifierInterface;
use ItPack\DisenchantIt\Contract\MagicLiteralVisitorInterface;
use ItPack\DisenchantIt\Contract\NamingContextExtractorInterface;
use ItPack\DisenchantIt\Contract\RoleClassifierInterface;
use ItPack\DisenchantIt\ValueObject\DisenchantScopeInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\NumberLiteral;
use ItPack\DisenchantIt\ValueObject\StringLiteral;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BitwiseAnd;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\BinaryOp\BitwiseXor;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects every literal (String_, LNumber, DNumber) in every method of every
 * class in a file, tagging each with its classified role and recording its
 * EXACT raw source token (e.g. `1_000_000`, `0x1F`) alongside the parsed
 * scalar value.
 *
 * Deliberately does not decide what to DO with a candidate — that's a
 * filtering/strategy concern layered on top of this raw collection, not this
 * visitor's job (keep detection and policy separate).
 */
final class MagicLiteralVisitor extends NodeVisitorAbstract implements MagicLiteralVisitorInterface
{
    /** @var list<LiteralInterface> */
    private array $found = [];
    private ?string $currentClass = null;
    private ?string $currentMethod = null;
    private string $code;

    public function __construct(
        private readonly RoleClassifierInterface $classifier,
        private readonly NamingContextExtractorInterface $extractor,
        string $code,
        private readonly ContentClassifierInterface $contentClassifier,
        private readonly int $maxHeredocBodyChars = DisenchantScopeInterface::DEFAULT_MAX_HEREDOC_BODY_CHARS,
    ) {
        $this->code = $code;
    }

    /**
     * In this visitor `enterNode` never redirects or mutates the tree, so it
     * only ever returns null (compatible with the parent's nullable union).
     */
    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\ClassLike && $node->name !== null) {
            $this->currentClass = $node->name->toString();
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->currentMethod = $node->name->toString();
        }

        if ($node instanceof Node\Stmt\EnumCase) {
            $this->currentMethod = 'case ' . $node->name->toString();
        }

        if ($node instanceof String_) {
            $nodeKind = $node->getAttribute('kind');

            // Heredoc / nowdoc bodies: collect the body as one string literal
            // (size-gated), leave interpolated strings for a later phase.
            if ($nodeKind === String_::KIND_HEREDOC || $nodeKind === String_::KIND_NOWDOC) {
                if ($this->isInsideClassConstDeclaration($node)) {
                    return null; // a constant's own declared value — not a usage site
                }

                $body = $node->value;

                // Size-gated: skip empty or oversized bodies (bounded cost for
                // the opt-in string-coverage scan; the limit comes from
                // DisenchantScope). Heredocs without interpolation parse as a
                // plain `String_`, so `$node->value` already holds the full body.
                if ($body === '' || strlen($body) > $this->maxHeredocBodyChars) {
                    return null;
                }

                $parent = $node->getAttribute('parent');
                $typedParent = $parent instanceof Node ? $parent : null;
                $role = $this->classifier->classify($node, $typedParent);

                $proto = new StringLiteral(
                    value: $body,
                    line: $node->getStartLine(),
                    role: $role,
                    className: $this->currentClass ?? '(unknown)',
                    methodName: $this->currentMethod ?? '(unknown)',
                    raw: $this->rawOf($node),
                    endLine: $node->getEndLine(),
                );

                $this->found[] = new StringLiteral(
                    value: $proto->value,
                    line: $proto->line,
                    role: $proto->role,
                    className: $proto->className,
                    methodName: $proto->methodName,
                    raw: $proto->raw,
                    endLine: $proto->endLine,
                    namingContext: $this->extractor->extract($node, $node->getStartLine(), $this->code),
                    subkind: $this->contentClassifier->classify($proto, false),
                    isClassnameString: $this->contentClassifier->isClassNameString($proto->value),
                );

                return null;
            }

            if ($this->isInsideClassConstDeclaration($node)) {
                return null; // this literal IS a constant's value — not a usage site
            }

            $parent = $node->getAttribute('parent');
            $typedParent = $parent instanceof Node ? $parent : null;
            $inBitwiseContext = $typedParent instanceof BitwiseOr
                || $typedParent instanceof BitwiseAnd
                || $typedParent instanceof BitwiseXor;
            $role = $this->classifier->classify($node, $typedParent);

            $proto = new StringLiteral(
                value: $node->value,
                line: $node->getStartLine(),
                role: $role,
                className: $this->currentClass ?? '(unknown)',
                methodName: $this->currentMethod ?? '(unknown)',
                raw: $this->rawOf($node),
                endLine: $node->getEndLine(),
            );

            $this->found[] = new StringLiteral(
                value: $proto->value,
                line: $proto->line,
                role: $proto->role,
                className: $proto->className,
                methodName: $proto->methodName,
                raw: $proto->raw,
                endLine: $proto->endLine,
                namingContext: $this->extractor->extract($node, $node->getStartLine(), $this->code),
                subkind: $this->contentClassifier->classify($proto, $inBitwiseContext),
                isClassnameString: $this->contentClassifier->isClassNameString($proto->value),
            );

            return null;
        }

        if ($node instanceof LNumber || $node instanceof DNumber) {
            if ($this->isInsideClassConstDeclaration($node)) {
                return null; // a numeric constant's OWN declared value — not a usage site
            }

            $parent = $node->getAttribute('parent');
            $typedParent = $parent instanceof Node ? $parent : null;
            $inBitwiseContext = $typedParent instanceof BitwiseOr
                || $typedParent instanceof BitwiseAnd
                || $typedParent instanceof BitwiseXor;
            $role = $this->classifier->classify($node, $typedParent);

            $proto = new NumberLiteral(
                kind: $node instanceof LNumber ? LiteralKind::Int->value : LiteralKind::Float->value,
                value: (string) $node->value,
                raw: $this->rawOf($node),
                line: $node->getStartLine(),
                role: $role,
                className: $this->currentClass ?? '(unknown)',
                methodName: $this->currentMethod ?? '(unknown)',
                namingContext: $this->extractor->extract($node, $node->getStartLine(), $this->code),
                endLine: $node->getEndLine(),
            );

            $this->found[] = new NumberLiteral(
                kind: $proto->kind,
                value: $proto->value,
                raw: $proto->raw,
                line: $proto->line,
                role: $proto->role,
                className: $proto->className,
                methodName: $proto->methodName,
                namingContext: $proto->namingContext,
                endLine: $proto->endLine,
                subkind: $this->contentClassifier->classify($proto, $inBitwiseContext),
            );

            return null;
        }

        return null;
    }

    /**
     * @return list<LiteralInterface>
     */
    public function getFound(): array
    {
        return $this->found;
    }

    /**
     * Recovers the exact source token for a node from its file offsets against
     * the original code, so `0x1F` stays `0x1F` (not `31`) and `1_000_000`
     * stays `1_000_000`.
     */
    private function rawOf(Node $node): string
    {
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();

        if ($start < 0 || $end < $start) {
            return (string) ($node->value ?? '');
        }

        return substr($this->code, $start, $end - $start + 1);
    }

    /**
     * Walks up from a node to see whether it sits inside a
     * `Node\Stmt\ClassConst` — i.e., IS a constant's declared value, such as
     * `private const string FOO = 'bar';` or `private const int MASK = 0x1F;`.
     * Detecting these as fresh candidates is what made earlier runs re-wrap
     * their own generated constants on every later pass, producing duplicate
     * declarations and self-referential values. A constant declaration's own
     * value must never be treated as a usage site.
     */
    private function isInsideClassConstDeclaration(Node $node): bool
    {
        $current = $node->getAttribute('parent');

        while ($current !== null) {
            if ($current instanceof Node\Stmt\ClassConst) {
                return true;
            }

            $current = $current instanceof Node
                ? $current->getAttribute('parent')
                : null;
        }

        return false;
    }
}
