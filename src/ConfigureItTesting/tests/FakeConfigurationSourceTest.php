<?php

declare(strict_types=1);

namespace ItPack\ConfigureItTesting\Tests;

use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureItTesting\Fake\FakeConfigurationSource;
use PHPUnit\Framework\TestCase;

final class FakeConfigurationSourceTest extends TestCase
{
    public function testDiscoverYieldsProvidedResources(): void
    {
        $source = FakeConfigurationSource::fromArray([
            'config/app.json' => 'json',
            'config/local.yaml' => 'yaml',
        ]);

        $resources = iterator_to_array($source->discover());

        self::assertCount(2, $resources);
        self::assertSame('config/app.json', $resources[0]->uri());
        self::assertSame(Format::JSON, $resources[0]->format());
        self::assertSame(Format::YAML, $resources[1]->format());
    }

    public function testVariadicResources(): void
    {
        $source = new FakeConfigurationSource(
            new \ItPack\ConfigureIt\ValueObject\ConfigurationResource('a.env', Format::ENV),
        );

        $resources = iterator_to_array($source->discover());

        self::assertCount(1, $resources);
        self::assertSame('a.env', $resources[0]->uri());
    }
}
