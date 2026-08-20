<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Swallowing exceptions via an empty `catch` block is forbidden (RunIt ADR-008,
 * M13.2): the original failure must be preserved, reported, or rethrown. An
 * intentional ignore must be explicit and documented through a reporter call,
 * which is impossible in an empty block.
 *
 * @implements Rule<Node\Stmt\Catch_>
 */
final class NoEmptyCatchRule extends AbstractSrcRule implements Rule
{
    public function __construct(string $srcRoot)
    {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Stmt\Catch_>
     */
    public function getNodeType(): string
    {
        return Node\Stmt\Catch_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if ($node->stmts !== []) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'Empty catch block silently swallows the exception; handle, report, or rethrow it (RunIt M13.2).',
        )->identifier('runit.noEmptyCatch')->build()];
    }
}
