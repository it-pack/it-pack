<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;
use ItPack\ConfigureIt\ValueObject\SourceKind;

class ConfigurationGraphProvenanceTest extends Unit
{
    public function testHigherPriorityMarksWinningNodeAsOverridden(): void
    {
        $graph = new ConfigurationGraph([
            $this->node('db.host', 'base', 'base', 10),
            $this->node('db.host', 'override', 'local', 20),
        ]);

        $winner = $graph->node('db.host');

        verify($winner->value())->equals('override');
        verify($winner->provenance()->overridden())->true();
        verify($winner->provenance()->overriddenSource())->equals('base');
    }

    public function testLowerPriorityDoesNotOverride(): void
    {
        $graph = new ConfigurationGraph([
            $this->node('db.host', 'base', 'base', 30),
            $this->node('db.host', 'weak', 'local', 5),
        ]);

        $kept = $graph->node('db.host');

        verify($kept->value())->equals('base');
        verify($kept->provenance()->overridden())->false();
    }

    public function testEqualPriorityKeepsLatestWithoutOverrideFlag(): void
    {
        $graph = new ConfigurationGraph([
            $this->node('db.host', 'first', 'base', 10),
            $this->node('db.host', 'second', 'local', 10),
        ]);

        verify($graph->node('db.host')->value())->equals('second');
        verify($graph->node('db.host')->provenance()->overridden())->false();
    }
    private function node(string $key, mixed $value, string $source, int $priority, string $shadowed = 'base'): ConfigurationNode
    {
        return new ConfigurationNode(
            $key,
            $value,
            $source,
            $priority,
            [],
            new Provenance($source, Format::YAML, 'Loader', $priority, SourceKind::File),
        );
    }
}
