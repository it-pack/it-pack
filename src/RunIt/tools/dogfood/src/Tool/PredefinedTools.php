<?php

declare(strict_types=1);

namespace Tools\Dogfood\Tool;

/**
 * The six RunIt dev-tooling runs represented as dogfood executions.
 *
 * Each entry names a composer script so the runner can execute it in a
 * subprocess with its own, independent bootstrap. Release validation reuses the
 * `quality` script, which is the project's release gate.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class PredefinedTools
{
    /**
     * @return list<DevTool>
     */
    public static function all(): array
    {
        return [
            self::test(),
            self::staticAnalysis(),
            self::architecture(),
            self::mutation(),
            self::benchmark(),
            self::releaseValidation(),
        ];
    }

    public static function test(): DevTool
    {
        return new DevTool(
            name: 'runit.dogfood.test',
            command: 'composer test',
            kind: 'cli.command',
            occurrenceType: 'runit.dogfood.test',
            snapshotType: 'runit.dogfood.test.summary',
        );
    }

    public static function staticAnalysis(): DevTool
    {
        return new DevTool(
            name: 'runit.dogfood.static_analysis',
            command: 'composer analyse',
            kind: 'cli.command',
            occurrenceType: 'runit.dogfood.static_analysis',
            snapshotType: 'runit.dogfood.static_analysis.summary',
        );
    }

    public static function architecture(): DevTool
    {
        return new DevTool(
            name: 'runit.dogfood.architecture',
            command: 'composer architecture',
            kind: 'cli.command',
            occurrenceType: 'runit.dogfood.architecture',
            snapshotType: 'runit.dogfood.architecture.summary',
        );
    }

    public static function mutation(): DevTool
    {
        return new DevTool(
            name: 'runit.dogfood.mutation',
            command: 'composer mutation',
            kind: 'cli.command',
            occurrenceType: 'runit.dogfood.mutation',
            snapshotType: 'runit.dogfood.mutation.summary',
        );
    }

    public static function benchmark(): DevTool
    {
        return new DevTool(
            name: 'runit.dogfood.benchmark',
            command: 'composer benchmark',
            kind: 'cli.command',
            occurrenceType: 'runit.dogfood.benchmark',
            snapshotType: 'runit.dogfood.benchmark.summary',
        );
    }

    public static function releaseValidation(): DevTool
    {
        return new DevTool(
            name: 'runit.dogfood.release_validation',
            command: 'composer quality',
            kind: 'cli.command',
            occurrenceType: 'runit.dogfood.release_validation',
            snapshotType: 'runit.dogfood.release_validation.summary',
        );
    }
}
