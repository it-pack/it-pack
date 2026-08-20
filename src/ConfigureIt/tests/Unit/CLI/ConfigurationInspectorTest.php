<?php

declare(strict_types=1);

namespace Tests\Unit\CLI;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\CLI\ConfigurationInspector;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\Provenance;

class ConfigurationInspectorTest extends Unit
{
    private function graph(): ConfigurationGraph
    {
        return new ConfigurationGraph([
            new ConfigurationNode(
                'db.host',
                'local-db',
                'local.json',
                20,
                [],
                new Provenance('local.json', Format::JSON, 'ItPack\ConfigureIt\Adapter\JsonLoader', 20, 'file')
                    ->asOverridden('base.json')
                    ->withProcessor('VariableProcessor'),
            ),
            new ConfigurationNode(
                'app.debug',
                true,
                'base.json',
                10,
                [],
                new Provenance('base.json', Format::JSON, 'ItPack\ConfigureIt\Adapter\JsonLoader', 10, 'file'),
            ),
        ]);
    }

    public function testExplainKeyRendersProvenance(): void
    {
        $text = new ConfigurationInspector()->explain($this->graph(), 'db.host');

        verify(\str_contains($text, 'db.host'))->true();
        verify(\str_contains($text, 'local-db'))->true();
        verify(\str_contains($text, 'JsonLoader'))->true();
        verify(\str_contains($text, 'overrode base.json'))->true();
        verify(\str_contains($text, 'VariableProcessor'))->true();
    }

    public function testExplainMissingKeyReportsAbsence(): void
    {
        $text = new ConfigurationInspector()->explain($this->graph(), 'nope');

        verify(\str_contains($text, 'not present'))->true();
    }

    public function testExplainWithoutKeyRendersTable(): void
    {
        $text = new ConfigurationInspector()->explain($this->graph());

        verify(\str_contains($text, 'KEY'))->true();
        verify(\str_contains($text, 'VALUE'))->true();
        verify(\str_contains($text, 'ADAPTER'))->true();
        verify(\str_contains($text, 'OVERRIDDEN'))->true();
        verify(\str_contains($text, 'db.host'))->true();
        verify(\str_contains($text, 'yes'))->true();
    }
}
