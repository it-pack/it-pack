<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\PromotionTargetInterface;
use ItPack\DisenchantIt\ValueObject\ConstantDeclaration;
use ItPack\DisenchantIt\ValueObject\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\Insertion;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralSubkind;
use ItPack\DisenchantIt\ValueObject\NamingContextInterface;
use ItPack\DisenchantIt\ValueObject\PromotionArtifact;
use ItPack\DisenchantIt\ValueObject\PromotionArtifactInterface;
use ItPack\DisenchantIt\ValueObject\Replacement;
use ItPack\DisenchantIt\ValueObject\TargetKind;

/**
 * Promotion target for a bit-flag set (Phase 3, S3.2). A group qualifies when
 * every member is an integer of sub-kind Bitmask (a power of two sitting in a
 * `|`/`&` bitwise operation) that shares one naming context (variable) with the
 * other members.
 *
 * It emits one `public const int <Base><Value> = <raw>;` per distinct flag
 * (e.g. `public const int Mode1 = 1;`) and rewrites each occurrence to
 * `self::<Base><Value>`. The combination is preserved as `self::Mode1 |
 * self::Mode2` rather than collapsed to a synthetic `ALL` constant — the
 * decoder is the bitwise OR of the individual flags, which is exactly what the
 * source expressed, and stays correct and idempotent on every pass.
 *
 * Registered ahead of BackedEnumTarget in the composite so a power-of-two set is
 * promoted to flags, not to an (equally-valid-but-less-intentional) enum.
 */
final readonly class FlagSetTarget implements PromotionTargetInterface
{
    public function supports(GroupedCandidateInterface $group): bool
    {
        $occurrences = $group->candidate->occurrences;

        if (count($occurrences) < 2) {
            return false;
        }

        if ($this->sharedVariable($occurrences) === null) {
            return false;
        }

        foreach ($occurrences as $lit) {
            if ($lit->kind !== LiteralKind::Int->value) {
                return false;
            }

            if ($lit->subkind !== LiteralSubkind::Bitmask) {
                return false;
            }

            $value = (int) $lit->value;
            if ($value <= 0 || ($value & ($value - 1)) !== 0) {
                return false; // not a power of two
            }
        }

        return true;
    }

    public function build(GroupedCandidateInterface $group): PromotionArtifactInterface
    {
        $occurrences = $group->candidate->occurrences;

        if ($occurrences === []) {
            return new PromotionArtifact([], [], TargetKind::FlagSet);
        }

        $variable = $this->sharedVariable($occurrences) ?? '';
        $base = $variable !== '' ? $this->pascalCase($variable) : 'Flag';

        $byClass = [];
        foreach ($occurrences as $lit) {
            $byClass[$lit->className][] = $lit;
        }

        $insertions = [];
        $replacements = [];

        foreach ($byClass as $className => $classOccurrences) {
            $valueToFlag = [];
            $constLines = [];

            foreach ($classOccurrences as $lit) {
                if (isset($valueToFlag[$lit->value])) {
                    continue;
                }

                $flagName = $base . $lit->raw;
                $valueToFlag[$lit->value] = $flagName;

                $constLines[] = new ConstantDeclaration(
                    name: $flagName,
                    valueExpression: $lit->raw,
                    typeKeyword: 'int',
                    visibility: 'public',
                )->toCodeLine();
            }

            $insertions[] = new Insertion($className, $constLines, false);

            foreach ($classOccurrences as $lit) {
                $replacements[] = new Replacement(
                    $lit->line,
                    $lit->needle(),
                    'self::' . $valueToFlag[$lit->value],
                );
            }
        }

        return new PromotionArtifact($insertions, $replacements, TargetKind::FlagSet);
    }

    /**
     * Returns the single shared variable name when every occurrence carries the
     * same one, otherwise null (a flag set must belong to one context).
     *
     * @param list<LiteralInterface> $occurrences
     */
    private function sharedVariable(array $occurrences): ?string
    {
        $variable = null;

        foreach ($occurrences as $lit) {
            $ctx = $lit->namingContext;
            if (!$ctx instanceof NamingContextInterface || $ctx->variableName === null) {
                return null;
            }

            if ($variable === null) {
                $variable = $ctx->variableName;
            } elseif ($variable !== $ctx->variableName) {
                return null;
            }
        }

        return $variable;
    }

    /**
     * PascalCases a variable name into a valid const-base prefix (mirrors
     * BackedEnumTarget's case naming, kept private to avoid a shared dependency).
     */
    private function pascalCase(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]+/', ' ', $value) ?? $value;
        $parts = array_filter(explode(' ', trim($clean)), static fn (string $p): bool => $p !== '');
        $name = implode('', array_map(ucfirst(...), $parts));

        if ($name === '') {
            $name = 'Flag';
        }

        if (ctype_digit($name[0])) {
            $name = 'V' . $name;
        }

        return $name;
    }
}
