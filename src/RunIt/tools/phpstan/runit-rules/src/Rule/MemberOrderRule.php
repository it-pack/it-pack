<?php

declare(strict_types=1);

namespace Tools\PhpStan\RunitRules\Rule;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tools\PhpStan\RunitRules\Support\AbstractSrcRule;

/**
 * Enforces the canonical class-member declaration order from the RunIt coding
 * standard (AGENTS.md / REVIEW R-3, refactoring plan T1.1 / T2.1):
 *
 *   trait `use` → constants → properties/hooks → constructor → static methods
 *   → instance methods; public before protected/private within a contiguous
 *   same-category run.
 *
 * The ordering is not observable at runtime, so this is a pure hygiene guard:
 * it freezes the normalization performed in Phase 1 so the documented convention
 * cannot silently regrow. The rule reports only the first deviation per type.
 *
 * @implements Rule<Node\Stmt\ClassLike>
 */
final class MemberOrderRule extends AbstractSrcRule implements Rule
{
    private const int RANK_TRAIT_USE = 1;
    private const int RANK_CONST = 2;
    private const int RANK_PROPERTY = 3;
    private const int RANK_CONSTRUCTOR = 4;
    private const int RANK_STATIC_METHOD = 5;
    private const int RANK_INSTANCE_METHOD = 6;

    public function getNodeType(): string
    {
        return Node\Stmt\ClassLike::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isInSrc($scope)) {
            return [];
        }

        if (!$node instanceof Node\Stmt\Class_
            && !$node instanceof Node\Stmt\Trait_
            && !$node instanceof Node\Stmt\Interface_
            && !$node instanceof Node\Stmt\Enum_) {
            return [];
        }

        $members = $node->stmts;
        $previousRank = 0;
        $previousVisibility = null;

        foreach ($members as $member) {
            $rank = $this->rankOf($member);

            if ($rank === null) {
                continue;
            }

            if ($rank < $previousRank) {
                $message = sprintf(
                    'Class member "%s" is declared out of canonical order (rank %d before a member of rank %d). '
                    . 'Expected order: trait use → constants → properties → constructor → static '
                    . 'methods → instance methods, '
                    . 'with public before protected/private within each group (RunIt T2.1).',

                    $this->describe($member),
                    $rank,
                    $previousRank,
                );

                return [RuleErrorBuilder::message($message)
                    ->identifier('runit.memberOrder')
                    ->build()];
            }

            $visibility = $this->visibilityOf($member);

            if ($rank === $previousRank
                && $visibility !== null
                && $previousVisibility !== null
                && $visibility < $previousVisibility) {
                return [RuleErrorBuilder::message(sprintf(
                    'Member "%s" narrows visibility within its group (public must precede protected/private). '
                    . 'Reorder so public members come first (RunIt T2.1).',
                    $this->describe($member),
                ))->identifier('runit.memberOrder')->build()];
            }

            if ($visibility !== null) {
                $previousVisibility = $visibility;
            }

            $previousRank = $rank;
        }

        return [];
    }

    private function rankOf(Node $member): ?int
    {
        if ($member instanceof Node\Stmt\TraitUse) {
            return self::RANK_TRAIT_USE;
        }

        if ($member instanceof Node\Stmt\ClassConst || $member instanceof Node\Stmt\EnumCase) {
            return self::RANK_CONST;
        }

        if ($member instanceof Node\Stmt\Property) {
            return self::RANK_PROPERTY;
        }

        if ($member instanceof Node\Stmt\ClassMethod) {
            if ($member->name->toLowerString() === '__construct') {
                return self::RANK_CONSTRUCTOR;
            }

            return $member->isStatic() ? self::RANK_STATIC_METHOD : self::RANK_INSTANCE_METHOD;
        }

        return null;
    }

    private function visibilityOf(Node $member): ?int
    {
        if ($member instanceof Node\Stmt\ClassConst) {
            return $this->visibilityLevel($member->isPublic(), $member->isProtected());
        }

        if ($member instanceof Node\Stmt\Property) {
            return $this->visibilityLevel($member->isPublic(), $member->isProtected());
        }

        if ($member instanceof Node\Stmt\ClassMethod) {
            return $this->visibilityLevel($member->isPublic(), $member->isProtected());
        }

        return null;
    }

    private function visibilityLevel(bool $isPublic, bool $isProtected): int
    {
        if ($isPublic) {
            return 0;
        }

        if ($isProtected) {
            return 1;
        }

        return 2;
    }

    private function describe(Node $member): string
    {
        if ($member instanceof Node\Stmt\TraitUse) {
            $names = array_map(static fn (Node\Name $t): string => $t->toString(), $member->traits);

            return 'use ' . implode(', ', $names);
        }

        if ($member instanceof Node\Stmt\ClassConst) {
            $first = $member->consts[0] ?? null;

            return $first instanceof Node\Const_ ? $first->name->toString() : 'const';
        }

        if ($member instanceof Node\Stmt\EnumCase) {
            return $member->name->toString();
        }

        if ($member instanceof Node\Stmt\Property) {
            $first = $member->props[0] ?? null;

            if ($first instanceof Node\Stmt\PropertyProperty) {
                return $first->name->toString();
            }

            return 'property';
        }

        if ($member instanceof Node\Stmt\ClassMethod) {
            return $member->name->toString() . '()';
        }

        return get_debug_type($member);
    }
}
