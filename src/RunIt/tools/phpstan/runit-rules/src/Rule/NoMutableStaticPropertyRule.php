<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Mutable static state is forbidden (RunIt AGENTS.md, M13.2). A static property
 * must be `readonly`; any other static property is mutable global-ish state.
 *
 * @implements Rule<Node\Stmt\Property>
 */
final class NoMutableStaticPropertyRule extends AbstractSrcRule implements Rule
{
    public function __construct(string $srcRoot)
    {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Stmt\Property>
     */
    public function getNodeType(): string
    {
        return Node\Stmt\Property::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if (!$node->isStatic() || $node->isReadonly()) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'Static property must be declared readonly; mutable static state is forbidden (RunIt M13.2).',
        )->identifier('runit.noMutableStatic.property')->build()];
    }
}
