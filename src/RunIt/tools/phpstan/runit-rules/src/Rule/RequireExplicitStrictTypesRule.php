<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Requires every library source file to declare `declare(strict_types=1)`.
 *
 * The directive is a root-level sibling of the namespace node (it must appear
 * before `namespace`), so the raw file is inspected rather than the namespace's
 * child statements.
 *
 * @implements Rule<Node\Stmt\Namespace_>
 */
final class RequireExplicitStrictTypesRule extends AbstractSrcRule implements Rule
{
    public function __construct(string $srcRoot)
    {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Stmt\Namespace_>
     */
    public function getNodeType(): string
    {
        return Node\Stmt\Namespace_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        $content = @file_get_contents($scope->getFile());

        if ($content !== false && preg_match('/declare\s*\(\s*strict_types\s*=\s*1\b/', $content) === 1) {
            return [];
        }

        return [RuleErrorBuilder::message(
            'Missing `declare(strict_types=1)`; every library file must enable strict typing (RunIt M13.2).',
        )->identifier('runit.requireStrictTypes')->build()];
    }
}
