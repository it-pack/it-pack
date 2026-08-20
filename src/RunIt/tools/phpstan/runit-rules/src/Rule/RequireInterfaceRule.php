<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Every concrete class must implement an appropriate interface (RunIt AGENTS.md,
 * M13.2). This keeps consumers and adapters programming against contracts, not
 * concrete types, and keeps the hexagon invertible.
 *
 * A bounded set of existing value-object, configuration, test-harness and
 * behavioural-helper classes are exempted (documented in docs/qa.md) because
 * extracting a semantic interface for them is a separate, tracked follow-up.
 *
 * @implements Rule<Node\Stmt\Class_>
 */
final class RequireInterfaceRule extends AbstractSrcRule implements Rule
{
    /**
     * @param list<string> $exemptDirectories relative directories (under src/) whose classes are exempt
     * @param list<string> $exemptFiles       relative file paths (under src/) individually exempt
     */
    public function __construct(
        string $srcRoot,
        private readonly array $exemptDirectories,
        private readonly array $exemptFiles,
    ) {
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

        if ($node->isAnonymous()) {
            return [];
        }

        $relativePath = $this->relativePath($scope);

        foreach ($this->exemptDirectories as $directory) {
            if (str_starts_with($relativePath, rtrim($directory, '/') . '/')) {
                return [];
            }
        }

        if (in_array($relativePath, $this->exemptFiles, true)) {
            return [];
        }

        if ($node->implements !== []) {
            return [];
        }

        return [RuleErrorBuilder::message(sprintf(
            'Concrete class %s must implement an interface (RunIt M13.2).',
            $node->name instanceof Node\Identifier ? $node->name->name : 'anonymous',
        ))->identifier('runit.requireInterface')->build()];
    }
}
