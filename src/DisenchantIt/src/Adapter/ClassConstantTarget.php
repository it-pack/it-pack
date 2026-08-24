<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\ConstantNameResolverInterface;
use ItPack\DisenchantIt\Contract\ConstantPlacementFactoryInterface;
use ItPack\DisenchantIt\Contract\PromotionTargetInterface;
use ItPack\DisenchantIt\ValueObject\ConstantDeclaration;
use ItPack\DisenchantIt\ValueObject\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\Insertion;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\PromotionArtifactInterface;
use ItPack\DisenchantIt\ValueObject\Replacement;
use ItPack\DisenchantIt\ValueObject\TargetKind;
use ItPack\DisenchantIt\ValueObject\Visibility;

/**
 * Promotion target that isolates today's constant-promotion behavior behind the
 * PromotionTargetInterface seam. For a detected group it emits exactly what
 * LineBasedRewriter has always produced: a `private const` per distinct class
 * (SameClass when one class, PerClass when several) and a `self::CONST`
 * replacement per occurrence.
 *
 * Output is byte-identical to the pre-Phase-2 pipeline; this class only relocates
 * the logic behind the new seam (verified by a fixture-diff test).
 */
final readonly class ClassConstantTarget implements PromotionTargetInterface
{
    public function __construct(
        private ConstantPlacementFactoryInterface $placements,
        private ConstantNameResolverInterface $resolver,
    ) {
    }

    public function supports(GroupedCandidateInterface $group): bool
    {
        return $group->candidate->count() >= 1;
    }

    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface
    {
        $occurrences = $group->candidate->occurrences;

        if ($occurrences === []) {
            return new PromotionArtifact([], [], TargetKind::Const);
        }

        $constantName = $this->resolver->resolve($occurrences[0]);
        $typeKeyword = LiteralKind::from($occurrences[0]->kind)->toPhpKeyword();

        // A heredoc/nowdoc body spans multiple lines: its declaration value is
        // the quoted body (not the multi-line token) and it is not collected
        // into SemConv (publishing a large SQL/JSON body is undesirable).
        $isMultiline = $occurrences[0]->endLine > $occurrences[0]->line;

        $byClass = [];
        foreach ($occurrences as $lit) {
            $byClass[$lit->className][] = $lit;
        }

        $insertions = [];
        $replacements = [];
        $placements = [];

        foreach ($byClass as $className => $classOccurrences) {
            $valueExpression = $isMultiline
                ? var_export($classOccurrences[0]->value, true)
                : $classOccurrences[0]->raw;
            if (!$isMultiline) {
                $placements[] = $this->placements->build(
                    className: $className,
                    constantName: $constantName,
                    value: $classOccurrences[0]->value,
                    type: $classOccurrences[0]->kind,
                    occurrences: $classOccurrences,
                );
            }

            $declarationLine = new ConstantDeclaration(
                name: $constantName,
                valueExpression: $valueExpression,
                typeKeyword: $typeKeyword,
                visibility: Visibility::Private,
            )->toCodeLine();

            $insertions[] = new Insertion($className, [$declarationLine], false);

            foreach ($classOccurrences as $lit) {
                [$needle, $endLine] = $isMultiline
                    ? [$lit->raw, $lit->endLine]
                    : [$lit->needle(), null];

                $replacements[] = new Replacement($lit->line, $needle, 'self::' . $constantName, $endLine);
            }
        }

        return new PromotionArtifact($insertions, $replacements, TargetKind::Const, $placements);
    }
}
