<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Constructors must not take an unbounded number of positional parameters. A
 * wide constructor is a composition-smell that hides cohesive groups of
 * dependencies and pushes callers toward positional-call mistakes.
 *
 * The threshold is deliberately set above the widest intentional domain value
 * object (immutable records such as `Snapshot` / `Occurrence`), so the rule
 * flags only genuinely oversized constructors. A bounded set of classes whose
 * constructor width is an accepted, tracked trade-off are exempted (mirroring
 * the `RequireInterfaceRule` exemption list).
 *
 * @implements Rule<Node\Stmt\ClassMethod>
 */
final class MaxConstructorParametersRule extends AbstractSrcRule implements Rule
{
    /**
     * @param int          $maxParameters maximum allowed constructor parameters
     * @param list<string> $exemptFiles   relative file paths (under src/) individually exempt
     */
    public function __construct(
        string $srcRoot,
        private readonly int $maxParameters,
        private readonly array $exemptFiles = [],
    ) {
        parent::__construct($srcRoot);
    }

    /**
     * @return class-string<\PhpParser\Node\Stmt\ClassMethod>
     */
    public function getNodeType(): string
    {
        return Node\Stmt\ClassMethod::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if ($node->name->name !== '__construct') {
            return [];
        }

        if (in_array($this->relativePath($scope), $this->exemptFiles, true)) {
            return [];
        }

        $count = count($node->params);

        if ($count <= $this->maxParameters) {
            return [];
        }

        $message = 'Constructor has %d parameters; the maximum allowed is %d '
            . '(RunIt T3.1). Extract a cohesive dependency group or builder.';

        return [RuleErrorBuilder::message(sprintf($message, $count, $this->maxParameters))
            ->identifier('runit.maxConstructorParameters')
            ->build()];
    }
}
