<?php

declare(strict_types=1);

namespace Tools\PhpStan\AsyncItRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\AsyncItRules\Support\AbstractSrcRule;

/**
 * Guard-clause / early-return style is required over `else` / `elseif`. Branching
 * that funnels through an `else` hides the primary flow behind a secondary one
 * and is a recurring source of the silent-wrong defects this codebase guards
 * against. Convert to an early return, a separate `if`, or a `match`.
 *
 * Registered once per node type (`Else_`, `ElseIf_`).
 *
 * @implements Rule<Node>
 */
final class ForbidElseRule extends AbstractSrcRule implements Rule
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

        return [RuleErrorBuilder::message(
            'Avoid `else` / `elseif`; use an early return, a separate `if`, or a `match` (AsyncIt M0).',
        )->identifier('asyncit.forbidElse')->build()];
    }
}
