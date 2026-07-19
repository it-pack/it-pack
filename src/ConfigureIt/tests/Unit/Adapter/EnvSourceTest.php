<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\EnvReader;
use ItPack\ConfigureIt\Adapter\EnvSource;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

class EnvSourceTest extends Unit
{
    public function testDiscoversSingleEnvResource(): void
    {
        $source = new EnvSource();
        $count = 0;

        foreach ($source->discover() as $resource) {
            $count++;
            verify($resource->format())->equals(Format::ENV);
        }

        verify($count)->equals(1);
    }
}

class EnvReaderTest extends Unit
{
    public function testReadsEnvironmentVariables(): void
    {
        $_ENV['CFG_TEST_KEY'] = 'value123';

        try {
            $reader = new EnvReader();
            $raw = $reader->read(new ConfigurationResource('env://environment', Format::ENV));

            verify($raw)->stringContainsString('CFG_TEST_KEY=value123');
        } finally {
            unset($_ENV['CFG_TEST_KEY']);
        }
    }

    public function testFiltersToRequestedVariables(): void
    {
        $_ENV['CFG_ONLY_A'] = 'a';
        $_ENV['CFG_ONLY_B'] = 'b';

        try {
            $reader = new EnvReader(['CFG_ONLY_A']);
            $raw = $reader->read(new ConfigurationResource('env://environment', Format::ENV));

            verify($raw)->stringContainsString('CFG_ONLY_A=a');
        } finally {
            unset($_ENV['CFG_ONLY_A'], $_ENV['CFG_ONLY_B']);
        }
    }

    public function testThrowsForNonEnvResource(): void
    {
        $reader = new EnvReader();

        $this->expectException(\ItPack\ConfigureIt\Exception\ReadException::class);

        $reader->read(new ConfigurationResource('x', Format::JSON));
    }
}
