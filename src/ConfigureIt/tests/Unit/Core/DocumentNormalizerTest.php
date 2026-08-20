<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationDocument;
use ItPack\ConfigureIt\Core\DocumentNormalizer;
use ItPack\ConfigureIt\ValueObject\Format;

class DocumentNormalizerTest extends Unit
{
    public function testFlattensNestedAssociativeArrays(): void
    {
        $normalizer = new DocumentNormalizer();

        $nodes = $normalizer->normalize(
            new ConfigurationDocument(['db' => ['host' => 'local', 'ports' => [1, 2]]], Format::JSON),
            5,
            'base',
        );

        $flat = [];
        foreach ($nodes as $node) {
            $flat[$node->key()] = $node->value();
        }

        verify($flat)->equals([
            'db.host' => 'local',
            'db.ports' => [1, 2],
        ]);
    }

    public function testPreservesPriorityAndOriginAndMintsProvenance(): void
    {
        $normalizer = new DocumentNormalizer();

        $nodes = $normalizer->normalize(new ConfigurationDocument(['a' => 1], Format::JSON), 3, 'src');

        verify($nodes[0]->priority())->equals(3);
        verify($nodes[0]->source())->equals('src');
        verify($nodes[0]->metadata())->equals([]);

        $provenance = $nodes[0]->provenance();
        verify($provenance->resourceUri())->equals('src');
        verify($provenance->format()->name)->equals('JSON');
        verify($provenance->adapter())->equals('unknown');
        verify($provenance->priority())->equals(3);
        verify($provenance->sourceKind())->equals('file');
        verify($provenance->processorTrail())->equals([]);
    }

    public function testProvenanceCarriesAdapterAndFormat(): void
    {
        $document = new ConfigurationDocument(
            ['db' => ['host' => 'local']],
            Format::YAML,
            'config/app.yaml',
            'ItPack\ConfigureIt\Adapter\YamlLoader',
        );

        $nodes = new DocumentNormalizer()->normalize($document, 1, 'config/app.yaml');

        $byKey = [];
        foreach ($nodes as $n) {
            $byKey[$n->key()] = $n;
        }

        $provenance = $byKey['db.host']->provenance();
        verify($provenance->format()->name)->equals('YAML');
        verify($provenance->adapter())->equals('ItPack\ConfigureIt\Adapter\YamlLoader');
        verify($provenance->sourceKind())->equals('file');
        verify($provenance->resourceUri())->equals('config/app.yaml');
    }
}
