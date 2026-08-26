<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Arbitrary PHP object serialization is prohibited (RunIt AGENTS.md, M13.2):
 * `serialize()` / `unserialize()` of opaque objects is a data-exfiltration and
 * stability risk. Explicit, deterministic normalization (the `*->normalize()`
 * value objects and the snapshot/serialization ports) is the supported path.
 *
 * @implements Rule<Node\Expr\FuncCall>
 */
final class ForbidArbitraryObjectSerializationRule extends AbstractSrcRule implements Rule
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

        $function = strtolower($name->toString());

        if (!in_array($function, ['serialize', 'unserialize'], true)) {
            return [];
        }

        $message = 'Arbitrary object serialization via %s() is forbidden; '
            . 'use deterministic normalization instead (RunIt M13.2).';

        return [RuleErrorBuilder::message(sprintf($message, $function))
            ->identifier('runit.forbidSerialization')
            ->build()];
    }
}
