<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Properties must be initialized: either declared `readonly`, given a default
 * value, or assigned through `$this->property = ...` somewhere in the class
 * (typically the constructor). Uninitialized properties are a defect class
 * PHPStan's `checkUninitializedProperties` reports globally; this src-scoped
 * variant enforces the same intent for the library without flagging PHPUnit
 * fixtures whose properties are populated in `setUp()` (RunIt M13.2).
 *
 * @implements Rule<Node\Stmt\Class_>
 */
final class RequireInitializedPropertiesRule extends AbstractSrcRule implements Rule
{
    public function __construct(string $srcRoot)
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

        $uninitialized = [];

        foreach ($node->getProperties() as $property) {
            if ($property->isStatic() || $property->isReadonly() || $property->type === null) {
                continue;
            }

            // A property with a `get` hook is computed on read and never stored,
            // so it cannot be "uninitialized" in the way this rule guards against.
            if ($property->hooks !== []) {
                continue;
            }

            foreach ($property->props as $prop) {
                if ($prop->default === null) {
                    $uninitialized[$prop->name->name] = true;
                }
            }
        }

        if ($uninitialized === []) {
            return [];
        }

        $finder = new NodeFinder();
        $assignments = $finder->findInstanceOf($node, Node\Expr\Assign::class);
        $assignments = [...$assignments, ...$finder->findInstanceOf($node, Node\Expr\AssignRef::class)];

        foreach ($assignments as $assignment) {
            $target = $assignment->var;

            if (!$target instanceof Node\Expr\PropertyFetch) {
                continue;
            }

            if (!$target->var instanceof Node\Expr\Variable || $target->var->name !== 'this') {
                continue;
            }

            if ($target->name instanceof Node\Identifier) {
                unset($uninitialized[$target->name->name]);
            }
        }

        $errors = [];

        foreach (array_keys($uninitialized) as $name) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Property $%s may be uninitialized; assign it in the constructor or declare it readonly (RunIt M13.2).',
                $name,
            ))->identifier('runit.requireInitializedProperty')->build();
        }

        return $errors;
    }
}
