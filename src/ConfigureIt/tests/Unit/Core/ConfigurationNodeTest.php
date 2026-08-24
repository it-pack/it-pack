<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationNode;

class ConfigurationNodeTest extends Unit
{
    public function testExposesValueMetadata(): void
    {
        $node = new ConfigurationNode('db.host', 'localhost', 'base.yaml', 10, ['origin' => 'base.yaml']);

        verify($node->key())->equals('db.host');
        verify($node->value())->equals('localhost');
        verify($node->source())->equals('base.yaml');
        verify($node->priority())->equals(10);
        verify($node->metadata())->equals(['origin' => 'base.yaml']);
    }

    public function testDefaultProvenanceReportsUnknownFormatAndAdapter(): void
    {
        $node = new ConfigurationNode('db.host', 'localhost', 'inline', 10);

        $provenance = $node->provenance();

        verify($provenance->format())->equals(\ItPack\ConfigureIt\ValueObject\Format::UNKNOWN);
        verify($provenance->adapter())->equals('unknown');
        verify($provenance->sourceKind())->equals('programmatic');
    }
}
