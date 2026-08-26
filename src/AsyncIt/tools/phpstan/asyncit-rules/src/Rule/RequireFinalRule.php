<?php

declare(strict_types=1);

namespace Tools\PhpStan\AsyncItRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\AsyncItRules\Support\AbstractSrcRule;

/**
 * Every concrete class must be `final` (AsyncIt AGENTS.md: "Every concrete class
 * must be final"). Abstract base classes, anonymous classes, interfaces, traits
 * and enums are exempt because they cannot be, or are not, concrete.
 *
 * @implements Rule<Node\Stmt\Class_>
 */
final class RequireFinalRule extends AbstractSrcRule implements Rule
{
    /**
     * @param list<string> $exemptFiles relative file paths (under src/) individually exempt
     */
    public function __construct(string $srcRoot, private readonly array $exemptFiles = [])
    {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Stmt\Class_>
     */
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if (in_array($this->relativePath($scope), $this->exemptFiles, true)) {
            return [];
        }

        if ($node->isAnonymous() || $node->isAbstract() || $node->isFinal()) {
            return [];
        }

        return [RuleErrorBuilder::message(sprintf(
            'Concrete class %s must be declared final (AsyncIt M1).',
            $node->name instanceof Node\Identifier ? $node->name->name : 'anonymous',
        ))->identifier('asyncit.requireFinal')->build()];
    }
}
