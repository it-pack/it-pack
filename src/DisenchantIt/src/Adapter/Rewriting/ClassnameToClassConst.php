<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter\Rewriting;

use ItPack\DisenchantIt\Contract\Rewriting\ClassnameToClassConstInterface;
use ItPack\DisenchantIt\Contract\SemConv\SemConv;
use ItPack\DisenchantIt\ValueObject\Candidate\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\Result\PromotionArtifactInterface;
use ItPack\DisenchantIt\ValueObject\Rewrite\Replacement;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;

/**
 * Opt-in promotion target: rewrites a fully-qualified class-name string to a
 * `<Short>::class` reference. Emits no declaration (the engine already provides
 * the `::class` constant) and is collected into SemConv only as an alias-free
 * reference, so it is wired into the composite ONLY when the scope enables
 * `convertFqcnToClassConst`.
 *
 * Pure source-text rewrite; never autoloads or resolves the referenced class.
 */
final readonly class ClassnameToClassConst implements ClassnameToClassConstInterface
{
    public function supports(GroupedCandidateInterface $group): bool
    {
        $occurrences = $group->candidate->occurrences;

        if ($occurrences === []) {
            return false;
        }

        foreach ($occurrences as $lit) {
            if (!$lit->isClassnameString) {
                return false;
            }
        }

        return true;
    }

    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface
    {
        $occurrences = $group->candidate->occurrences;

        if ($occurrences === []) {
            return new PromotionArtifact([], [], TargetKind::Fqcn);
        }

        $replacements = [];
        foreach ($occurrences as $lit) {
            $short = $this->shortName($lit->value);

            // The exact source token (quotes + FQCN) is the search needle so the
            // rewriter replaces the literal verbatim, preserving surrounding code.
            $replacements[] = new Replacement($lit->line, $lit->raw, $short . '::class');
        }

        // No constant declaration: `::class` is provided by PHP itself.
        return new PromotionArtifact([], $replacements, TargetKind::Fqcn);
    }

    private function shortName(string $fullyQualified): string
    {
        $segments = explode('\\', $fullyQualified);

        return end($segments);
    }
}
