<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\ConfigureIt\Adapter\CompositeReader;
use ItPack\ConfigureIt\Contract\ConfigurationReaderInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * @covers \ItPack\ConfigureIt\Adapter\CompositeReader
 */
class CompositeReaderTest extends Unit
{
    public function testReturnsFirstSupportingReader(): void
    {
        $resource = new ConfigurationResource('/x', Format::JSON);
        $reader = new CompositeReader([$this->refusingReader(), $this->supportingReader()]);

        verify($reader->read($resource))->equals('content');
    }

    public function testSupportsReportsAnyReader(): void
    {
        $resource = new ConfigurationResource('/x', Format::JSON);
        $reader = new CompositeReader([$this->refusingReader(), $this->supportingReader()]);

        verify($reader->supports($resource))->true();
        verify(new CompositeReader([$this->refusingReader()])->supports($resource))->false();
    }

    public function testThrowsWhenNoReaderSupports(): void
    {
        $resource = new ConfigurationResource('/x', Format::JSON);
        $reader = new CompositeReader([$this->refusingReader()]);

        $this->expectException(InvalidArgumentException::class);
        $reader->read($resource);
    }
    private function supportingReader(): ConfigurationReaderInterface
    {
        return new class () implements ConfigurationReaderInterface {
            public function read(ConfigurationResourceInterface $resource): string
            {
                return 'content';
            }

            public function supports(ConfigurationResourceInterface $resource): bool
            {
                return true;
            }
        };
    }

    private function refusingReader(): ConfigurationReaderInterface
    {
        return new class () implements ConfigurationReaderInterface {
            public function read(ConfigurationResourceInterface $resource): string
            {
                return '';
            }

            public function supports(ConfigurationResourceInterface $resource): bool
            {
                return false;
            }
        };
    }
}
