<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Adapter;

use ItPack\DisenchantIt\Contract\ClosedSetDetectorInterface;
use ItPack\DisenchantIt\ValueObject\Candidate;
use ItPack\DisenchantIt\ValueObject\CandidateInterface;
use ItPack\DisenchantIt\ValueObject\GroupedCandidate;
use ItPack\DisenchantIt\ValueObject\GroupedCandidateInterface;
use ItPack\DisenchantIt\ValueObject\LiteralInterface;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\NamingContextInterface;

/**
 * Flags detected candidates that form a closed set suitable for promotion to a
 * backed enum (Phase 2, S2.3).
 *
 * Detection groups occurrences by their shared naming context (a single variable
 * / parameter / array-key name), NOT by literal value — a closed set is exactly
 * a small, stable group of distinct values that all live in one such context.
 * When the aggregated group qualifies it is emitted as one closed GroupedCandidate
 * (covering every value occurrence), otherwise the original per-value candidates
 * pass through unchanged to be promoted as ordinary constants.
 *
 * A group qualifies when:
 *   (a) every member shares one LiteralKind (all string, or all int),
 *   (b) every member shares one variable / parameter / array-key name, and
 *   (c) the distinct-value count is small and stable (>= 2, <= MAX_DISTINCT).
 *
 * Array-key literals are excluded from set membership (the key is the context,
 * not a member), so `['status' => 'new', ...]` yields cases New/Active/Closed
 * without dragging the `'status'` key into the enum as a case.
 */
final readonly class ClosedSetDetector implements ClosedSetDetectorInterface
{
    private const int MIN_DISTINCT = 2;
    private const int MAX_DISTINCT = 12;

    /**
     * @param array<string, CandidateInterface> $candidates keyed by grouping key.
     * @return array<string, GroupedCandidateInterface> re-keyed by context (closed
     *         sets) or by original literal value (ordinary candidates).
     */
    public function detect(array $candidates): array
    {
        /** @var array<string, list<LiteralInterface>> $byContext */
        $byContext = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate->occurrences as $lit) {
                $byContext[$this->contextKey($lit)][] = $lit;
            }
        }

        /** @var array<string, array{variable: string, members: list<LiteralInterface>}> $closed */
        $closed = [];
        foreach ($byContext as $key => $occurrences) {
            if (!str_starts_with($key, 'ctx:')) {
                continue;
            }
            $members = $this->setMembers($occurrences);
            if ($this->isClosedSet($members)) {
                $closed[$key] = ['variable' => substr($key, 4), 'members' => $members];
            }
        }

        $groups = [];

        foreach ($closed as $key => $info) {
            $groups[$key] = new GroupedCandidate(
                new Candidate($info['members'][0]->value, $info['variable'], $info['members']),
                true,
                $info['variable'],
            );
        }

        // Pass through the original per-value candidates that are not wholly
        // consumed by a closed context (so they keep being promoted as constants).
        foreach ($candidates as $valueKey => $candidate) {
            $insideClosed = false;
            foreach ($candidate->occurrences as $lit) {
                if (isset($closed[$this->contextKey($lit)])) {
                    $insideClosed = true;
                    break;
                }
            }
            if ($insideClosed) {
                continue;
            }
            $groups[$valueKey] = new GroupedCandidate($candidate, false, null);
        }

        return $groups;
    }

    /**
     * @param list<LiteralInterface> $occurrences
     */
    private function isClosedSet(array $occurrences): bool
    {
        if (count($occurrences) < self::MIN_DISTINCT) {
            return false;
        }

        $kind = $occurrences[0]->kind;
        $distinct = [];
        foreach ($occurrences as $lit) {
            if ($lit->kind !== $kind) {
                return false;
            }
            $ctx = $lit->namingContext;
            if (!$ctx instanceof NamingContextInterface || $ctx->variableName === null) {
                return false;
            }
            $distinct[$lit->value] = true;
        }

        $distinctCount = count($distinct);

        return $distinctCount >= self::MIN_DISTINCT && $distinctCount <= self::MAX_DISTINCT;
    }

    /**
     * @param list<LiteralInterface> $occurrences
     * @return list<LiteralInterface>
     */
    private function setMembers(array $occurrences): array
    {
        return array_values(array_filter(
            $occurrences,
            static fn (LiteralInterface $lit): bool => $lit->role !== LiteralRole::ArrayKeyLiteral
                && $lit->role !== LiteralRole::ArrayKey,
        ));
    }

    private function contextKey(LiteralInterface $lit): string
    {
        if ($lit->role === LiteralRole::ArrayKeyLiteral || $lit->role === LiteralRole::ArrayKey) {
            return 'val:' . $lit->value;
        }

        $ctx = $lit->namingContext;
        if ($ctx instanceof NamingContextInterface && $ctx->variableName !== null) {
            return 'ctx:' . $ctx->variableName;
        }

        return 'val:' . $lit->value;
    }
}
