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
    private const string KIND = 'cli.command';
    private const string PREFIX = 'runit.dogfood';

    /**
     * @return list<DevTool>
     */
    public static function all(): array
    {
        return [
            self::composerValidate(),
            self::rectorDry(),
            self::csDry(),
            self::staticAnalysis(),
            self::architecture(),
            self::apiCheck(),
            self::lengthLint(),
            self::test(),
            self::security(),
            self::benchmark(),
        ];
    }

    public static function composerValidate(): DevTool
    {
        return self::makeDevTool(
            'composer_validate',
            'composer validate --strict',
        );
    }

    public static function rectorDry(): DevTool
    {
        return self::makeDevTool(
            'rector_dry',
            'composer refactor:dry'
        );
    }

    public static function csDry(): DevTool
    {
        return self::makeDevTool(
            'cs_fixer_dry',
            'composer @cs:check'
        );
    }

    public static function staticAnalysis(): DevTool
    {
        return self::makeDevTool(
            'static_analysis',
            'composer analyse'
        );
    }

    public static function architecture(): DevTool
    {
        return self::makeDevTool(
            'architecture',
            'composer architecture'
        );
    }

    public static function apiCheck(): DevTool
    {
        return self::makeDevTool(
            'api_check',
            'composer api:check'
        );
    }

    public static function lengthLint(): DevTool
    {
        return self::makeDevTool(
            'line_length_lint',
            'composer lint:length'
        );
    }

    public static function test(): DevTool
    {
        return self::makeDevTool(
            'test',
            'composer test'
        );
    }

    public static function security(): DevTool
    {
        return self::makeDevTool(
            'security_audit',
            'composer security:audit'
        );
    }

    public static function benchmark(): DevTool
    {
        return self::makeDevTool(
            'benchmark',
            'composer benchmark'
        );
    }

    private static function makeDevTool(string $name, string $command): DevTool
    {
        return new DevTool(
            name: self::PREFIX . '.' . $name,
            command: $command,
            kind: self::KIND,
            occurrenceType: self::PREFIX . '.' . $name,
            snapshotType: self::PREFIX . '.' . $name. '.summary',
        );
    }
}
