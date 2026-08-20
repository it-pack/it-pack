<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

class ConfigurationSnapshotProvenanceTest extends Unit
{
    public function testSnapshotExposesPerNodeProvenance(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'localhost',
                'config/app.yaml',
                10,
                [],
                new Provenance('config/app.yaml', Format::YAML, 'YamlLoader', 10, 'file'),
            ),
        ]);

        $snapshot = ConfigurationSnapshot::fromGraph($graph);

        $provenance = $snapshot->provenance('db.host');

        verify($provenance)->notNull();
        verify($provenance?->resourceUri())->equals('config/app.yaml');
        verify($provenance?->format()->name)->equals('YAML');
        verify($provenance?->adapter())->equals('YamlLoader');
        verify($snapshot->get('db.host'))->equals('localhost');
    }

    public function testMissingKeyHasNullProvenance(): void
    {
        $snapshot = ConfigurationSnapshot::fromGraph(new ConfigurationGraph());

        verify($snapshot->provenance('nope.nope'))->null();
    }
}
