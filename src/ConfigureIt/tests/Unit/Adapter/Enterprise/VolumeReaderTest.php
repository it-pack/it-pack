<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Enterprise;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\Enterprise\VolumeReader;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;
use ReflectionMethod;
use Tests\Support\Extension\VirtualFileSystem;

/**
 * @covers \ItPack\ConfigureIt\Adapter\Enterprise\VolumeReader
 */
final class VolumeReaderTest extends Unit
{
    use VirtualFileSystem;

    private string $dir;

    public function testSupportsEnvVolumeSchemesOnly(): void
    {
        $reader = new VolumeReader();

        verify($reader->supports(new ConfigurationResource('configmap://x', Format::ENV)))->true();
        verify($reader->supports(new ConfigurationResource('k8s-secret://x', Format::ENV)))->true();
        verify($reader->supports(new ConfigurationResource('docker-secret://x', Format::ENV)))->true();
    }

    public function testRejectsNonEnvFormatAndUnknownScheme(): void
    {
        $reader = new VolumeReader();

        verify($reader->supports(new ConfigurationResource('configmap://x', Format::JSON)))->false();
        verify($reader->supports(new ConfigurationResource('http://x', Format::ENV)))->false();
    }

    public function testReadProducesTrimmedKeyValueLinesAndSkipsSubdirs(): void
    {
        $reader = new VolumeReader();
        $resource = new ConfigurationResource('configmap://' . $this->dir, Format::ENV);

        $body = $reader->read($resource);

        verify($body)->equals("app_name=demo\ndb_host=db.internal");
    }

    public function testReadThrowsWhenUnsupported(): void
    {
        $reader = new VolumeReader();
        $resource = new ConfigurationResource('configmap://' . $this->dir, Format::JSON);

        $this->expectException(ReadException::class);
        $this->expectExceptionMessage('VolumeReader cannot read resource');

        $reader->read($resource);
    }

    public function testReadThrowsWhenVolumeMissing(): void
    {
        $reader = new VolumeReader();
        $resource = new ConfigurationResource('configmap:///nonexistent/' . uniqid(), Format::ENV);

        $this->expectException(ReadException::class);
        $this->expectExceptionMessage('Configuration volume not found');

        $reader->read($resource);
    }

    public function testPathOfFallsBackToRawUri(): void
    {
        $reader = new VolumeReader();
        $method = new ReflectionMethod($reader, 'pathOf');

        verify($method->invoke($reader, 'file:///etc/x'))->equals('file:///etc/x');
    }

    protected function _before(): void
    {
        $this->dir = $this->vfsPath('/cfg-vol-u-' . uniqid());
        mkdir($this->dir);
        file_put_contents($this->dir . '/app_name', "demo\n");
        file_put_contents($this->dir . '/db_host', "db.internal\r\n");
        mkdir($this->dir . '/subdir');
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            $path = $this->dir . '/' . $item;
            if (is_dir($path)) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($this->dir);
    }
}
