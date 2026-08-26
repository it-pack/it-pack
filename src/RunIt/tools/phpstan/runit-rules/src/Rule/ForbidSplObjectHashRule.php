<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * `spl_object_hash()` must not be used as a cache key for callable identity. A
 * freed object's slot can be recycled by a new object, so a fresh unattributed
 * callable can inherit the previous callable's resolved metadata (the S5 defect
 * from Phase 0). Object identity must go through a `WeakMap` keyed on the
 * callable object itself.
 *
 * @implements Rule<Node\Expr\FuncCall>
 */
final class ForbidSplObjectHashRule extends AbstractSrcRule implements Rule
{
    public function __construct(string $srcRoot)
    {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Expr\FuncCall>
     */
    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        $name = $node->name;

        if (!$name instanceof Node\Name) {
            return [];
        }

        if (strtolower($name->toString()) !== 'spl_object_hash') {
            return [];
        }

        $message = 'spl_object_hash() must not be used as a cache key; '
            . 'use a WeakMap keyed on the callable object identity (RunIt T3.1).';

        return [RuleErrorBuilder::message($message)
            ->identifier('runit.forbidSplObjectHash')
            ->build()];
    }
}
