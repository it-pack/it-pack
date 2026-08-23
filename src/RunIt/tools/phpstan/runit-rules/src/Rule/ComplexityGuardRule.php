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
 * Guards against runaway control-flow complexity in `src/`.
 *
 * It counts branch-bearing nodes (if/elseif/else, switch/case, loops, try/catch,
 * ternary, coalesce, match arms) as a proxy for cognitive complexity. A method
 * whose count exceeds `$threshold` is flagged. The threshold is tuned to the
 * current worst offender, so the rule is a *regression guard*: today nothing is
 * flagged, but a newly-added over-complex method is caught before it merges.
 *
 * This complements the existing "no else/switch" convention: that discipline
 * already keeps most methods small, and this rule catches the ones that still
 * grow.
 *
 * @implements Rule<Node\Stmt\ClassMethod>
 */
final class ComplexityGuardRule extends AbstractSrcRule implements Rule
{
    public function __construct(string $srcRoot, private readonly int $threshold)
    {
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

        $complexity = $this->complexity($node);

        if ($complexity <= $this->threshold) {
            return [];
        }

        return [RuleErrorBuilder::message(sprintf(
            'Method %s has control-flow complexity %d (threshold %d). '
            . 'Extract a helper or simplify the branch structure (RunIt P3, T3.2).',
            $node->name->name ?? 'anonymous',
            $complexity,
            $this->threshold,
        ))->identifier('runit.complexity')->build()];
    }

    private function complexity(Node\Stmt\ClassMethod $method): int
    {
        $finder = new NodeFinder();
        $branches = $finder->find(
            $method->stmts ?? [],
            static fn (Node $node): bool => self::branchWeight($node) > 0,
        );

        $total = 0;

        foreach ($branches as $branch) {
            $total += self::branchWeight($branch);
        }

        return $total;
    }

    private static function branchWeight(Node $node): int
    {
        return match (true) {
            $node instanceof Node\Stmt\If_,
            $node instanceof Node\Stmt\ElseIf_,
            $node instanceof Node\Stmt\Else_,
            $node instanceof Node\Stmt\Switch_,
            $node instanceof Node\Stmt\Case_,
            $node instanceof Node\Stmt\While_,
            $node instanceof Node\Stmt\Do_,
            $node instanceof Node\Stmt\Foreach_,
            $node instanceof Node\Stmt\For_,
            $node instanceof Node\Stmt\TryCatch,
            $node instanceof Node\Stmt\Catch_,
            $node instanceof Node\Expr\Ternary,
            $node instanceof Node\Expr\BinaryOp\Coalesce,
            $node instanceof Node\Expr\Match_ => 1,
            $node instanceof Node\MatchArm => 1,
            default => 0,
        };
    }
}
