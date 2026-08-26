<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\ValueObject\DiagnosticsReport;
use ReflectionClass;

/**
 * Pure value-object coverage for the resolved-graph diagnostics report:
 * assembly, immutability, the serializable form and every summary branch
 * (empty counts, override attribution, unresolved variables).
 *
 * @covers \ItPack\ConfigureIt\ValueObject\DiagnosticsReport
 */
final class DiagnosticsReportTest extends Unit
{
    public function testAssemblesEveryChannelVerbatim(): void
    {
        $report = new DiagnosticsReport(
            self::overrides(),
            ['local.yaml' => 2, 'base.yaml' => 1],
            ['YAML' => 3],
            ['app.name'],
        );

        verify($report->overrides)->equals(self::overrides());
        verify($report->bySource)->equals(['local.yaml' => 2, 'base.yaml' => 1]);
        verify($report->byFormat)->equals(['YAML' => 3]);
        verify($report->unresolved)->equals(['app.name']);
    }

    public function testReportIsDeeplyImmutable(): void
    {
        $report = new DiagnosticsReport([], ['a' => 1], [], []);

        verify(new ReflectionClass(DiagnosticsReport::class)->isReadOnly())->true();

        // Arrays are handed out by value, so a caller cannot mutate the report.
        $counts = $report->bySource;
        $counts['a'] = 99;

        verify($report->bySource)->equals(['a' => 1]);

        $array = $report->toArray();
        $array['bySource'] = 'tampered';

        verify($report->toArray()['bySource'])->equals(['a' => 1]);
    }

    /**
     * @param list<array{key:string, source:string, shadowed:?string}> $overrides
     * @param list<string> $unresolved
     *
     * @dataProvider predicateMatrix
     */
    public function testPredicatesReflectTheirChannelOnly(
        array $overrides,
        array $unresolved,
        bool $hasOverrides,
        bool $hasUnresolved,
    ): void {
        $report = new DiagnosticsReport($overrides, [], [], $unresolved);

        verify($report->hasOverrides())->equals($hasOverrides);
        verify($report->hasUnresolved())->equals($hasUnresolved);
    }

    /**
     * @return array<string, array{list<array{key:string, source:string, shadowed:?string}>, list<string>, bool, bool}>
     */
    public function predicateMatrix(): array
    {
        return [
            'clean graph'      => [[], [], false, false],
            'overrides only'   => [self::overrides(), [], true, false],
            'unresolved only'  => [[], ['app.name'], false, true],
            'both channels'    => [self::overrides(), ['app.name'], true, true],
        ];
    }

    public function testToArrayIsSerializable(): void
    {
        $report = new DiagnosticsReport(
            self::overrides(),
            ['local.yaml' => 2],
            ['YAML' => 2],
            ['app.name'],
        );

        $array = $report->toArray();

        verify(array_keys($array))->equals(['overrides', 'bySource', 'byFormat', 'unresolved']);
        verify($array['overrides'])->equals(self::overrides());
        verify($array['bySource'])->equals(['local.yaml' => 2]);
        verify($array['byFormat'])->equals(['YAML' => 2]);
        verify($array['unresolved'])->equals(['app.name']);
        verify(json_encode($array))->notEmpty();
    }

    public function testSummaryOfAnEmptyReportMarksEveryChannelAsAbsent(): void
    {
        $summary = new DiagnosticsReport([], [], [], [])->summary();

        verify(explode("\n", $summary))->equals([
            'Configuration diagnostics:',
            '  nodes by source: (none)',
            '  nodes by format: (none)',
            '  overrides: none',
        ]);
    }

    public function testSummaryRendersCountsAsJson(): void
    {
        $summary = new DiagnosticsReport([], ['base.yaml' => 3], ['YAML' => 2, 'JSON' => 1], [])->summary();

        verify(explode("\n", $summary))->equals([
            'Configuration diagnostics:',
            '  nodes by source: {"base.yaml":3}',
            '  nodes by format: {"YAML":2,"JSON":1}',
            '  overrides: none',
        ]);
    }

    public function testSummaryAttributesOverridesAndFallsBackToUnknown(): void
    {
        $summary = new DiagnosticsReport(self::overrides(), [], [], [])->summary();

        verify(explode("\n", $summary))->equals([
            'Configuration diagnostics:',
            '  nodes by source: (none)',
            '  nodes by format: (none)',
            '  overrides:',
            '    db.host <- local.yaml (overrode base.yaml)',
            // A null shadowed source is reported as "unknown" rather than blank.
            '    db.port <- env:// (overrode unknown)',
        ]);
    }

    public function testSummaryAppendsUnresolvedVariables(): void
    {
        $summary = new DiagnosticsReport([], [], [], ['app.name', 'db.dsn'])->summary();

        verify(explode("\n", $summary))->equals([
            'Configuration diagnostics:',
            '  nodes by source: (none)',
            '  nodes by format: (none)',
            '  overrides: none',
            '  unresolved variables: app.name, db.dsn',
        ]);
    }

    public function testSummaryCombinesEveryChannel(): void
    {
        $summary = new DiagnosticsReport(
            [['key' => 'db.host', 'source' => 'local.yaml', 'shadowed' => 'base.yaml']],
            ['local.yaml' => 1],
            ['YAML' => 1],
            ['app.name'],
        )->summary();

        verify(explode("\n", $summary))->equals([
            'Configuration diagnostics:',
            '  nodes by source: {"local.yaml":1}',
            '  nodes by format: {"YAML":1}',
            '  overrides:',
            '    db.host <- local.yaml (overrode base.yaml)',
            '  unresolved variables: app.name',
        ]);
    }
    /**
     * @return list<array{key:string, source:string, shadowed:?string}>
     */
    private static function overrides(): array
    {
        return [
            ['key' => 'db.host', 'source' => 'local.yaml', 'shadowed' => 'base.yaml'],
            ['key' => 'db.port', 'source' => 'env://', 'shadowed' => null],
        ];
    }
}
