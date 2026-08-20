<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;
use Tools\PhpStan\RunitRules\Support\TypeNode;

/**
 * Forbids the `resource` type in signatures and property types.
 *
 * `resource` is the only type from the AGENTS.md untyped vocabulary that is never
 * acceptable in RunIt: it cannot be serialized, redacted, or observed safely.
 * The other documented untyped types (`array`, `string`, `int`, `float`, `bool`,
 * `object`, `callable`, `iterable`) remain permitted.
 *
 * @implements Rule<Node>
 */
final class ForbiddenUnsafeTypeRule extends AbstractSrcRule implements Rule
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
            $errors = [];

            foreach ($node->getParams() as $param) {
                if (TypeNode::containsResource($param->type)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Parameter $%s uses the forbidden unsafe type resource (RunIt M13.2).',
                        self::parameterName($param),
                    ))->identifier('runit.forbiddenUnsafe.param')->build();
                }
            }

            if (TypeNode::containsResource($node->getReturnType())) {
                $errors[] = RuleErrorBuilder::message(
                    'Return type uses the forbidden unsafe type resource (RunIt M13.2).',
                )->identifier('runit.forbiddenUnsafe.return')->build();
            }

            return $errors;
        }

        if ($node instanceof Node\Stmt\Property && TypeNode::containsResource($node->type)) {
            return [RuleErrorBuilder::message(
                'Property type uses the forbidden unsafe type resource (RunIt M13.2).',
            )->identifier('runit.forbiddenUnsafe.property')->build()];
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
