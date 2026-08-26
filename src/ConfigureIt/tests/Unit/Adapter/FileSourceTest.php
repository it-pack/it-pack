<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Contract\ValueObject\ConfigurationResourceInterface;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

class FileSourceTest extends Unit
{
    private string $dir;

    public function testDiscoversOnlyConfiguredExtensionsRecursively(): void
    {
        $source = new FileSource($this->dir);
        $uris = [];

        foreach ($source->discover() as $resource) {
            $uris[] = $resource->uri();
            verify($resource)->instanceOf(ConfigurationResourceInterface::class);
            verify($resource->format())->instanceOf(Format::class);
        }

        verify(count($uris))->equals(3);
        verify(in_array($this->dir . '/ignore.txt', $uris, true))->false();
    }

    public function testMissingDirectoryYieldsNothing(): void
    {
        $source = new FileSource($this->dir . '/does-not-exist');
        $count = 0;

        foreach ($source->discover() as $resource) {
            $count++;
        }

        verify($count)->equals(0);
    }

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-src-' . uniqid();
        mkdir($this->dir . '/nested', 0o700, true);
        file_put_contents($this->dir . '/a.php', '<?php return [];');
        file_put_contents($this->dir . '/b.json', '{}');
        file_put_contents($this->dir . '/nested/c.php', '<?php return [];');
        file_put_contents($this->dir . '/ignore.txt', 'x');
    }

    protected function _after(): void
    {
        $this->remove($this->dir);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path), ['.', '..']) as $item) {
            $target = $path . '/' . $item;
            is_dir($target) ? $this->remove($target) : unlink($target);
        }

        rmdir($path);
    }
}

class FileReaderTest extends Unit
{
    public function testReadsFileContent(): void
    {
        $file = sys_get_temp_dir() . '/cfg-read-' . uniqid() . '.php';
        file_put_contents($file, 'hello');
        $reader = new FileReader();

        verify($reader->read(new ConfigurationResource($file, Format::PHP)))->equals('hello');

        unlink($file);
    }

    public function testThrowsForMissingFile(): void
    {
        $reader = new FileReader();

        $this->expectException(\ItPack\ConfigureIt\Exception\ReadException::class);

        $reader->read(new ConfigurationResource('/no/such/file.php', Format::PHP));
    }
}
