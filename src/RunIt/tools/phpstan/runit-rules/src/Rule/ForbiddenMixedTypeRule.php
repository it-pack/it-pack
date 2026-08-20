<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Forbids implicit (undeclared) types, i.e. parameters, return types and
 * property types that omit an explicit type hint and therefore default to `mixed`.
 *
 * Explicit `mixed` remains permitted per AGENTS.md for genuinely opaque or
 * polymorphic boundaries (serializers, context/occurrence/snapshot payloads,
 * recorders, middleware, and polymorphic validators). The disciplined contract
 * is therefore: *declare a type everywhere*; reach for explicit `mixed` only at
 * those documented boundaries. This rule enforces the first half of that
 * contract (RunIt M13.2).
 *
 * @implements Rule<Node>
 */
final class ForbiddenMixedTypeRule extends AbstractSrcRule implements Rule
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

        if ($node instanceof Node\FunctionLike) {
            $isConstructor = $node instanceof Node\Stmt\ClassMethod
                && $node->name->name === '__construct';
            $isClosure = $node instanceof Node\Expr\Closure
                || $node instanceof Node\Expr\ArrowFunction;

            $errors = [];

            // Inline closures are pragmatic; only class methods, functions and
            // constructors must declare their parameter types.
            if (!$isClosure) {
                foreach ($node->getParams() as $param) {
                    if ($param->type === null) {
                        $errors[] = RuleErrorBuilder::message(sprintf(
                            'Parameter $%s is missing an explicit type (implicit mixed is forbidden, RunIt M13.2).',
                            self::parameterName($param),
                        ))->identifier('runit.forbiddenMixed.implicitParam')->build();
                    }
                }
            }

            // Constructors and inline closures have no meaningful return type to
            // declare.
            if (!$isConstructor && !$isClosure && $node->getReturnType() === null) {
                $errors[] = RuleErrorBuilder::message(
                    'Return type is missing (implicit mixed is forbidden, RunIt M13.2).',
                )->identifier('runit.forbiddenMixed.implicitReturn')->build();
            }

            return $errors;
        }

        if ($node instanceof Node\Stmt\Property && $node->type === null) {
            return [RuleErrorBuilder::message(
                'Property is missing an explicit type (implicit mixed is forbidden, RunIt M13.2).',
            )->identifier('runit.forbiddenMixed.implicitProperty')->build()];
        }

        return [];
    }

    private static function parameterName(Node\Param $param): string
    {
        $var = $param->var;

        if (!$var instanceof Node\Expr\Variable) {
            return '?';
        }

        $name = $var->name;

        return is_string($name) ? $name : '?';
    }
}
