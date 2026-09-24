<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Exception\ReadException;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;
use Tests\Support\Extension\VirtualFileSystem;

/**
 * @covers \ItPack\ConfigureIt\Adapter\FileReader
 */
class FileReaderCoverageTest extends Unit
{
    use VirtualFileSystem;

    private string $file;

    public function testReadsExistingFile(): void
    {
        file_put_contents($this->file, 'hello');
        $reader = new FileReader();
        $resource = new ConfigurationResource($this->file, Format::JSON);

        verify($reader->read($resource))->equals('hello');
        verify($reader->supports($resource))->true();
    }

    public function testSupportsReportsNonFiles(): void
    {
        $resource = new ConfigurationResource($this->file . '.missing', Format::JSON);

        verify(new FileReader()->supports($resource))->false();
    }

    public function testThrowsWhenFileMissing(): void
    {
        $reader = new FileReader();

        $this->expectException(ReadException::class);
        $reader->read(new ConfigurationResource($this->file . '.missing', Format::JSON));
    }

    public function testThrowsWhenFileUnreadable(): void
    {
        file_put_contents($this->file, 'secret');
        chmod($this->file, 0o000);

        $reader = new FileReader();

        try {
            @$reader->read(new ConfigurationResource($this->file, Format::JSON));
            self::fail('Expected ReadException for an unreadable file.');
        } catch (ReadException $e) {
            verify(str_contains($e->getMessage(), 'Unable to read'))->true();
        }
    }

    protected function _before(): void
    {
        $this->file = $this->vfsPath('/cfg-fr-' . uniqid() . '.json');
    }

    protected function _after(): void
    {
        if (is_file($this->file)) {
            @chmod($this->file, 0o600);
            @unlink($this->file);
        }
    }
}
