<?php

declare(strict_types=1);

namespace ItPack\DeriveItRunit;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * Runs DeriveIt operations as first-class RunIt executions
 * (CONSOLIDATION_PLAN.md §5.4 / C4).
 *
 * The callable receives a {@see DeriveEventSinkInterface} bound to the running
 * execution's scope; handing it to any DeriveIt facade method attaches every
 * pipeline fact to that execution:
 *
 * ```php
 * $source = DeriveItExecutions::run(
 *     $runtime,
 *     'derive.flat',
 *     fn (DeriveEventSinkInterface $sink): string => DeriveIt::derive($class, new DeriveConfig(), $sink),
 * );
 * ```
 *
 * Neither package imports the other: this helper is the only meeting point.
 */
final class DeriveItExecutions
{
    /**
     * Canonical execution kind for derivation runs (CONSOLIDATION_PLAN.md §5.3).
     */
    public const string EXECUTION_KIND = 'derivation.run';

    private function __construct()
    {
    }

    /**
     * The execution definition for a named derivation run, e.g. `derive.family`.
     */
    public static function definition(string $name): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString($name),
            ExecutionKind::fromString(self::EXECUTION_KIND),
        );
    }

    /**
     * Runs `$derivation` inside a new execution and hands it the bound telemetry
     * sink. The callable's return value is passed through unchanged.
     *
     * @template TResult
     *
     * @param callable(DeriveEventSinkInterface): TResult $derivation
     *
     * @return TResult
     */
    public static function run(RunItInterface $runtime, string $name, callable $derivation): mixed
    {
        return $runtime->run(
            self::definition($name),
            static fn ($scope): mixed => $derivation(new RunItDerivationEventSink($scope)),
        );
    }
}
