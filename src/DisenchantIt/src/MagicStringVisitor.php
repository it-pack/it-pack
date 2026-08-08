<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects every String_ literal in every method of every class in a file,
 * tagging each with its classified role. Deliberately does not decide what
 * to DO with a candidate — that's a filtering/strategy concern layered on
 * top of this raw collection, not this visitor's job (keep detection and
 * policy separate, same as ConfigureIt keeps loader-selection separate from
 * document-construction).
 */
final class MagicStringVisitor extends NodeVisitorAbstract
{
    /** @var list<StringLiteral> */
    private array $found = [];

    private ?string $currentClass = null;
    private ?string $currentMethod = null;

    public function __construct(private readonly RoleClassifier $classifier)
    {
    }

    public function enterNode(Node $node): void
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
            if ($this->isInsideClassConstDeclaration($node)) {
                return; // this literal IS a constant's value — not a usage site
            }

            $parent = $node->getAttribute('parent');
            $role = $this->classifier->classify($node, $parent);

            $this->found[] = new StringLiteral(
                value: $node->value,
                line: $node->getStartLine(),
                role: $role,
                className: $this->currentClass ?? '(unknown)',
                methodName: $this->currentMethod ?? '(unknown)',
            );
        }
    }

    /**
     * Walks up from a String_ node to see whether it sits inside a
     * `Node\Stmt\ClassConst` — i.e. IS a constant's declared value, such as
     * `private const string FOO = 'bar';`. Detecting these as fresh
     * candidates is what made earlier runs of disenchant.php re-wrap their
     * own generated constants on every subsequent pass, producing duplicate
     * declarations and, worse, self-referential values. A constant
     * declaration's own value must never be treated as a usage site.
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

    /** @return list<StringLiteral> */
    public function getFound(): array
    {
        return $this->found;
    }
}
