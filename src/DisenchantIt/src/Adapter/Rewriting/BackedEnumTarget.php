<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Rewriting;

use ItPack\DisenchantIt\Adapter\Grouping\CandidateFilter;
use ItPack\DisenchantIt\Adapter\Grouping\ClosedSetDetector;
use ItPack\DisenchantIt\Adapter\Naming\NameSlugifierInterface;
use ItPack\DisenchantIt\Contract\Rewriting\PromotionTargetInterface;
use ItPack\DisenchantIt\ValueObject\Candidate\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralKind;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifactInterface;
use ItPack\DisenchantIt\ValueObject\Rewrite\Insertion;
use ItPack\DisenchantIt\ValueObject\Rewrite\Replacement;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;

/**
 * Promotion target for a closed set of same-kind literals (flagged by
 * ClosedSetDetector). Emits a backed enum — `enum <Name>: <string|int> { case
 * <Case> = '<raw>'; ... }` inserted at file top level after the owning class —
 * and rewrites each literal occurrence to `<Name>::<Case>`.
 *
 * Case names are produced through the shared NameSlugifierInterface port
 * ('Value' fallback), so naming cannot drift from FlagSetTarget's const
 * prefixes.
 *
 * Idempotent by construction: on a second pass the enum's own backing values are
 * excluded by CandidateFilter (role EnumBackingValue), and already-rewritten
 * usages no longer contain the raw literal token, so neither is re-promoted.
 */
final readonly class BackedEnumTarget implements PromotionTargetInterface
{
    public function __construct(
        private readonly NameSlugifierInterface $nameSlugifier,
    ) {
    }

    public function supports(GroupedCandidateInterface $group): bool
    {
        return $group->isClosedSet;
    }

    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface
    {
        $occurrences = $group->candidate->occurrences;

        if ($occurrences === []) {
            return new PromotionArtifact([], [], TargetKind::Enum);
        }

        $kind = LiteralKind::from($occurrences[0]->kind);
        $backing = $kind === LiteralKind::Int ? 'int' : 'string';
        $enumName = $this->enumName($group->proposedEnumName);

        $className = $this->firstClassName($occurrences);

        $valueToCase = $this->caseNameMap($occurrences);

        $enumLines = ['enum ' . $enumName . ': ' . $backing . ' {'];
        $emitted = [];
        foreach ($occurrences as $lit) {
            if (isset($emitted[$lit->value])) {
                continue;
            }
            $emitted[$lit->value] = true;
            $enumLines[] = '    case ' . $valueToCase[$lit->value] . ' = ' . $lit->raw . ';';
        }
        $enumLines[] = '}';

        $insertions = [new Insertion($className, $enumLines, true)];

        $replacements = [];
        foreach ($occurrences as $lit) {
            $replacements[] = new Replacement(
                $lit->line,
                $lit->needle(),
                $enumName . '::' . $valueToCase[$lit->value],
            );
        }

        return new PromotionArtifact($insertions, $replacements, TargetKind::Enum);
    }

    /**
     * @param list<LiteralInterface> $occurrences
     * @return array<string, string> literal value => case name
     */
    private function caseNameMap(array $occurrences): array
    {
        $map = [];
        $used = [];
        foreach ($occurrences as $lit) {
            $base = $this->nameSlugifier->pascalCase($lit->value, 'Value');
            $name = $base;
            $suffix = 2;
            while (isset($used[strtolower($name)])) {
                $name = $base . $suffix;
                ++$suffix;
            }
            $used[strtolower($name)] = true;
            $map[$lit->value] = $name;
        }

        return $map;
    }

    private function enumName(?string $proposed): string
    {
        if ($proposed !== null && $proposed !== '') {
            return $this->nameSlugifier->pascalCase($proposed, 'Value');
        }

        return 'Disenchanted';
    }

    /**
     * @param list<LiteralInterface> $occurrences
     */
    private function firstClassName(array $occurrences): string
    {
        foreach ($occurrences as $lit) {
            return $lit->className;
        }

        return '(unknown)';
    }
}
