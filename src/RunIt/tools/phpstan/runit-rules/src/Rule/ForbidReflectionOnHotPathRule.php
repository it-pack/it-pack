<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Forbids Reflection API usage on the execution hot path.
 *
 * Reflection is only permitted in cold paths (attribute/mapping metadata readers).
 * The execution, activity and domain hot paths must avoid per-call reflection for
 * performance and determinism (see AGENTS.md performance requirements).
 *
 * @implements Rule<Node>
 */
final class ForbidReflectionOnHotPathRule extends AbstractSrcRule implements Rule
{
    /**
     * @param list<class-string> $hotPathNamespaces
     */
    /**
     * @param list<class-string> $hotPathNamespaces
     * @param class-string<\PhpParser\Node> $nodeType
     */
    public function __construct(
        private readonly array $hotPathNamespaces,
        private readonly string $nodeType,
        string $srcRoot,
    ) {
        parent::__construct($srcRoot);
    }

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

        $name = null;

        if (!($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name)
            && !($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name)) {
            return [];
        }

        $name = $node->class->toString();

        if (stripos($name, 'Reflection') !== 0) {
            return [];
        }

        $namespace = $scope->getNamespace();

        if ($namespace === null) {
            return [];
        }

        foreach ($this->hotPathNamespaces as $prefix) {
            if (str_starts_with($namespace, $prefix)) {
                return [RuleErrorBuilder::message(sprintf(
                    'Reflection usage (%s) is forbidden on the execution hot path %s (RunIt M13.2).',
                    $name,
                    $namespace,
                ))->identifier('runit.forbidReflection.hotPath')->build()];
            }
        }

        return [];
    }
}
