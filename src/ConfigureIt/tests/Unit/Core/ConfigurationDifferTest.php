<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;

use function count;

use ItPack\ConfigureIt\Core\ConfigurationDiffer;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

class ConfigurationDifferTest extends Unit
{
    public function testDetectsAddedRemovedAndChangedWithProvenance(): void
    {
        $base = new ConfigurationGraph([
            $this->node('a', 1, 'base.json', 10),
            $this->node('b', 2, 'base.json', 10),
        ]);

        $other = new ConfigurationGraph([
            $this->node('a', 1, 'base.json', 10),
            $this->node('b', 20, 'local.json', 20),
            $this->node('c', 3, 'local.json', 20),
        ]);

        $diff = new ConfigurationDiffer()->diff($base, $other);

        verify($diff->empty())->false();
        verify($diff->added)->equals(['c' => 3]);
        verify($diff->removed)->equals([]);

        verify(count($diff->changed))->equals(1);
        verify($diff->changed[0]['key'])->equals('b');
        verify($diff->changed[0]['from'])->equals(2);
        verify($diff->changed[0]['to'])->equals(20);
        verify($diff->changed[0]['toProvenance']['resourceUri'])->equals('local.json');
        verify($diff->changed[0]['fromProvenance']['resourceUri'])->equals('base.json');
    }

    public function testIdenticalGraphsProduceEmptyDiff(): void
    {
        $base = new ConfigurationGraph([$this->node('a', 1, 'base.json', 10)]);
        $other = new ConfigurationGraph([$this->node('a', 1, 'base.json', 10)]);

        $diff = new ConfigurationDiffer()->diff($base, $other);

        verify($diff->empty())->true();
        verify($diff->summary())->equals('Configuration graphs are identical.');
    }
    private function node(string $key, mixed $value, string $source, int $priority): ConfigurationNode
    {
        return new ConfigurationNode(
            $key,
            $value,
            $source,
            $priority,
            [],
            new Provenance($source, Format::JSON, 'JsonLoader', $priority, SourceKind::File),
        );
    }
}
