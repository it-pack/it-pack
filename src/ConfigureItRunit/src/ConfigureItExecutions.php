<?php

declare(strict_types=1);

namespace ItPack\ConfigureItRunit;

use ItPack\ConfigureIt\Contract\Execution\ResolutionReporterInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\RunItInterface;

/**
 * Runs ConfigureIt resolution as a first-class RunIt execution (M14.7).
 *
 * The callable receives a {@see ResolutionReporterInterface} bound to the running
 * execution's scope; handing it to any ConfigureIt resolver attaches every
 * resolution fact to that execution:
 *
 * ```php
 * $snapshot = ConfigureItExecutions::run(
 *     $runtime,
 *     'config.resolve',
 *     static fn (ResolutionReporterInterface $reporter): ConfigurationSnapshotInterface =>
 *         $resolver->resolve($reporter),
 * );
 * ```
 *
 * Neither package imports the other: this helper is the only meeting point.
 */
final class ConfigureItExecutions
{
    /**
     * Canonical execution kind for configuration resolution runs.
     */
    public const string EXECUTION_KIND = 'configuration.resolution';

    private function __construct()
    {
    }

    /**
     * The execution definition for a named configuration resolution run.
     */
    public static function definition(string $name): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString($name),
            ExecutionKind::fromString(self::EXECUTION_KIND),
        );
    }

    /**
     * Runs `$resolution` inside a new RunIt execution and hands it the bound
     * resolution reporter. The callable's return value is passed through
     * unchanged.
     *
     * @template TResult
     *
     * @param callable(ResolutionReporterInterface): TResult $resolution
     *
     * @return TResult
     */
    public static function run(RunItInterface $runtime, string $name, callable $resolution): mixed
    {
        return $runtime->run(
            self::definition($name),
            static fn ($scope): mixed => $resolution(new RunItResolutionReporter($scope)),
        );
    }
}
