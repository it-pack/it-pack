<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationDocumentFactory;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * @covers \ItPack\ConfigureIt\Core\ConfigurationDocumentFactory
 */
class ConfigurationDocumentFactoryTest extends Unit
{
    public function testCreateReturnsNewInstance(): void
    {
        $factory = new ConfigurationDocumentFactory();

        self::assertInstanceOf(ConfigurationDocumentFactory::class, $factory->create());
    }

    public function testBuildProducesDocument(): void
    {
        $document = new ConfigurationDocumentFactory()->build(
            ['a' => 1],
            Format::JSON,
            'origin',
            'adapter',
            'layer',
        );

        verify($document->toArray())->equals(['a' => 1]);
        verify($document->format())->equals(Format::JSON);
    }
}
