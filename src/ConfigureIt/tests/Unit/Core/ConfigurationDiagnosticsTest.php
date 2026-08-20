<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationDiagnostics;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Execution\ResolutionReporter;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

class ConfigurationDiagnosticsTest extends Unit
{
    public function testReportsOverridesAndUnresolvedVariables(): void
    {
        $reporter = new ResolutionReporter();
        $diagnostics = new ConfigurationDiagnostics($reporter);

        $graph = new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'base',
                'base.yaml',
                10,
                [],
                new Provenance('base.yaml', Format::YAML, 'YamlLoader', 10, 'file'),
            ),
            new ConfigurationNode(
                'db.host',
                'local',
                'local.yaml',
                20,
                [],
                new Provenance('local.yaml', Format::YAML, 'YamlLoader', 20, 'file')->asOverridden('base.yaml'),
            ),
            new ConfigurationNode(
                'app.name',
                '${missing}',
                'local.yaml',
                20,
                [],
                new Provenance('local.yaml', Format::YAML, 'YamlLoader', 20, 'file'),
            ),
        ]);

        $report = $diagnostics->report($graph);

        verify($report->hasOverrides())->true();
        verify(\count($report->overrides))->equals(1);
        verify($report->overrides[0]['key'])->equals('db.host');
        verify($report->overrides[0]['shadowed'])->equals('base.yaml');

        verify($report->hasUnresolved())->true();
        verify($report->unresolved[0])->equals('app.name');

        verify($report->bySource['local.yaml'])->equals(2);
        verify($report->byFormat['YAML'])->equals(2);

        $observations = $reporter->observations();
        verify(\count($observations))->equals(2);
    }

    public function testEmptyReportForCleanGraph(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('a', 1, 'base.yaml', 10, [], new Provenance('base.yaml', Format::YAML, 'YamlLoader', 10, 'file')),
        ]);

        $report = new ConfigurationDiagnostics()->report($graph);

        verify($report->hasOverrides())->false();
        verify($report->hasUnresolved())->false();
        verify($report->overrides)->equals([]);
    }
}
