<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Forbids by-reference `foreach` binding (`foreach ($x as &$v)`).
 *
 * By-reference iteration silently aliases the source collection and is a common
 * source of non-obvious mutation bugs. Values should be handled explicitly.
 *
 * @implements Rule<Node\Stmt\Foreach_>
 */
final class ExplicitForeachByRefRule extends AbstractSrcRule implements Rule
{
    public function __construct(string $srcRoot)
    {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Stmt\Foreach_>
     */
    public function getNodeType(): string
    {
        return Node\Stmt\Foreach_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope) || !$node->byRef) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'By-reference `foreach` binding is forbidden; iterate by value and assign explicitly (RunIt M13.2).',
        )->identifier('runit.explicitForeach.byRef')->build()];
    }
}
