<?php

declare(strict_types=1);

namespace Tools\PhpStan\AsyncItRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ItPack\AsyncIt\Contract\ValueObjectInterface;
use Tools\PhpStan\AsyncItRules\Support\AbstractSrcRule;

/**
 * Forbids constructing concrete (named) types outside Factory / Builder
 * namespaces.
 *
 * AsyncIt builds collaborators through injected factories and builders (AGENTS.md:
 * "new only in Factories/Builders passed via DI"), which keeps the hexagon
 * testable and the dependency direction explicit. Internal self / static
 * construction (value-object `with()` / `add()` shaping) and the explicitly
 * exempt namespaces (e.g. conformance fixtures) are allowed.
 *
 * @implements Rule<Node\Expr\New_>
 */
final class ForbidNewOutsideFactoryBuilderRule extends AbstractSrcRule implements Rule
{
    /**
     * @param list<string> $exemptNamespaces namespace prefixes exempt from the rule
     */
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly array $exemptNamespaces = [],
        string $srcRoot = '',
    ) {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Expr\New_>
     */
    public function getNodeType(): string
    {
        return Node\Expr\New_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        $class = $node->class;

        if ($class instanceof Node\Name) {
            $name = $class->toString();

            // self / static / parent construction is internal value-object shaping.
            if (in_array($name, ['self', 'static', 'parent'], true)) {
                return [];
            }
        }

        $namespace = $scope->getNamespace();

        if ($namespace === null) {
            return [];
        }

        if ($this->isAllowedNamespace($namespace)) {
            return [];
        }

        foreach ($this->exemptNamespaces as $prefix) {
            if (str_starts_with($namespace, $prefix)) {
                return [];
            }
        }

        $constructed = $class instanceof Node\Name ? $class->toString() : 'anonymous';

        $type = $scope->getType($node);
        $classNames = $type->getObjectClassNames();
        $allInternal = $classNames !== [];
        foreach ($classNames as $className) {
            if (!$this->reflectionProvider->hasClass($className)) {
                $allInternal = false;
                continue;
            }

            $reflection = $this->reflectionProvider->getClass($className);
            $allInternal = $allInternal && $reflection->isBuiltin();
            if ($reflection->implementsInterface(ValueObjectInterface::class)
                || $reflection->implementsInterface(\Throwable::class)) {
                return [];
            }
        }

        if ($allInternal) {
            return [];
        }

        return [RuleErrorBuilder::message(sprintf(
            'Direct construction of %s outside a Factory/Builder namespace is forbidden; use an injected factory (AsyncIt M1).',
            $constructed,
        ))->identifier('asyncit.forbidNew.outsideFactory')->build()];
    }

    private function isAllowedNamespace(string $namespace): bool
    {
        return str_contains($namespace, '\\Factory\\')
            || str_contains($namespace, '\\Builder\\')
            || str_ends_with($namespace, '\\Factory')
            || str_ends_with($namespace, '\\Builder');
    }
}
