<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Assignment to a static property is forbidden mutable static state (RunIt
 * AGENTS.md, M13.2). Readonly static properties are initialised once and are
 * therefore exempt.
 *
 * @implements Rule<Node>
 */
final class NoMutableStaticAssignmentRule extends AbstractSrcRule implements Rule
{
    /**
     * @param class-string<\PhpParser\Node> $nodeType
     */
    public function __construct(string $nodeType, string $srcRoot)
    {
        parent::__construct($srcRoot);
        $this->nodeType = $nodeType;
    }

    /** @var class-string<\PhpParser\Node> */
    private string $nodeType;

    /**
     * @return class-string<\PhpParser\Node>
     */
    public function getNodeType(): string
    {
        return $this->nodeType;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if (!$node instanceof Node\Expr\Assign && !$node instanceof Node\Expr\AssignRef) {
            return [];
        }

        $var = $node->var;

        if (!$var instanceof Node\Expr\StaticPropertyFetch) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'Assignment to a static property is forbidden; mutable static state is prohibited (RunIt M13.2).',
        )->identifier('runit.noMutableStatic.assignment')->build()];
    }
}
