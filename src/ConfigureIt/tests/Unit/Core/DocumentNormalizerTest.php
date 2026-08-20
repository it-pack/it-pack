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

    public function testPreservesPriorityAndOriginAndMetadata(): void
    {
        $normalizer = new DocumentNormalizer();

        $nodes = $normalizer->normalize(new ConfigurationDocument(['a' => 1], Format::JSON), 3, 'src');

        verify($nodes[0]->priority())->equals(3);
        verify($nodes[0]->source())->equals('src');
        verify($nodes[0]->metadata())->equals(['origin' => 'src', 'format' => 'document']);
    }
}
