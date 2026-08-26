<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\Client\FakeRemoteKeyValueClient;
use ItPack\ConfigureIt\Adapter\Enterprise\RemoteJsonReader;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Enterprise\RemoteJsonReader
 */
final class RemoteJsonReaderTest extends Unit
{
    public function testSupportsMatchingSchemeAndJson(): void
    {
        $reader = new RemoteJsonReader('consul', new FakeRemoteKeyValueClient('{}'));

        verify($reader->supports(new ConfigurationResource('consul://h/app', Format::JSON)))->true();
    }

    public function testRejectsWrongSchemeOrNonJson(): void
    {
        $reader = new RemoteJsonReader('consul', new FakeRemoteKeyValueClient('{}'));

        verify($reader->supports(new ConfigurationResource('etcd://h/app', Format::JSON)))->false();
        verify($reader->supports(new ConfigurationResource('consul://h/app', Format::YAML)))->false();
    }

    public function testReadDelegatesToClient(): void
    {
        $reader = new RemoteJsonReader('consul', new FakeRemoteKeyValueClient('{"a":1}'));

        verify($reader->read(new ConfigurationResource('consul://h/app', Format::JSON)))->equals('{"a":1}');
    }

    public function testReadThrowsWhenUnsupported(): void
    {
        $reader = new RemoteJsonReader('consul', new FakeRemoteKeyValueClient('{}'));

        $this->expectException(ReadException::class);
        $this->expectExceptionMessage('RemoteJsonReader cannot read resource');

        $reader->read(new ConfigurationResource('etcd://h/app', Format::JSON));
    }
}
