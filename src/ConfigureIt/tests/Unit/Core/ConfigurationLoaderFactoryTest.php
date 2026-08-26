<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Core\ConfigurationLoaderFactory;
use ItPack\ConfigureIt\Exception\ResolutionException;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * @covers \ItPack\ConfigureIt\Core\ConfigurationLoaderFactory
 */
class ConfigurationLoaderFactoryTest extends Unit
{
    public function testBuildReturnsSupportingLoader(): void
    {
        $factory = new ConfigurationLoaderFactory([new JsonLoader()]);

        $loader = $factory->build(Format::JSON);

        verify($loader instanceof JsonLoader)->true();
    }

    public function testBuildThrowsWhenNoLoaderSupports(): void
    {
        $factory = new ConfigurationLoaderFactory([new JsonLoader()]);

        $this->expectException(ResolutionException::class);
        $factory->build(Format::YAML);
    }

    public function testCreateReturnsFactory(): void
    {
        $factory = ConfigurationLoaderFactory::create([new JsonLoader()]);

        self::assertInstanceOf(ConfigurationLoaderFactory::class, $factory);
        verify($factory->build(Format::JSON) instanceof JsonLoader)->true();
    }
}
