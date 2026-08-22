<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

use ItPack\ConfigureIt\Contract\Execution\ExecutionContextInterface;
use ItPack\ConfigureIt\ValueObject\FlagDefinition;

/**
 * Evaluates feature flags declared in the resolved configuration.
 *
 * Flags are a thin, schema-driven layer built on top of the resolved snapshot
 * (see docs/ADDITIONAL_FEATURES_PLAN.md §8): they introduce no new source of
 * truth. Definitions are read from a single key (default `feature_flags`) and
 * combined with an {@see ExecutionContextInterface} to decide whether each flag
 * is on.
 */
interface FlagEvaluatorInterface
{
    /**
     * All declared flag definitions, keyed by flag name.
     *
     * @return array<string, FlagDefinition>
     */
    public function definitions(): array;

    public function has(string $flag): bool;

    public function isEnabled(string $flag, ExecutionContextInterface $ctx): bool;

    /**
     * Resolved state of every declared flag for the given context.
     *
     * @return array<string, bool>
     */
    public function evaluate(ExecutionContextInterface $ctx): array;
}
