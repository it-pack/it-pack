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
}
