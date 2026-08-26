<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Forbids the `var` keyword for property declarations; visibility must be explicit.
 *
 * @implements Rule<Node\Stmt\Property>
 */
final class NoVarRule extends AbstractSrcRule implements Rule
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

        $hasVisibility = ($node->flags
            & (Class_::MODIFIER_PUBLIC | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PRIVATE)) !== 0;

        if ($hasVisibility) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'Property declared with the `var` keyword is forbidden; declare explicit visibility (RunIt M13.2).',
        )->identifier('runit.noVar')->build()];
    }
}
