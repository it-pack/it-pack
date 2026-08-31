<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\SemConv\FileSemConvStore;

final class FileSemConvStoreTest extends Unit
{
    private function existingFile(string $contents = 'stored'): string
    {
        $p = tempnam(sys_get_temp_dir(), 'semconv');
        file_put_contents($p, $contents);

        return $p;
    }

    public function testLoadReturnsNullWhenMissing(): void
    {
        $store = new FileSemConvStore('/no/such/file.json');

        $this->assertNull($store->load());
    }

    public function testLoadReturnsContents(): void
    {
        $p = $this->existingFile();
        try {
            $store = new FileSemConvStore($p);
            $this->assertSame('stored', $store->load());
        } finally {
            unlink($p);
        }
    }

    public function testLoadReturnsNullForEmptyFile(): void
    {
        $p = $this->existingFile('');
        try {
            $store = new FileSemConvStore($p);
            $this->assertNull($store->load());
        } finally {
            unlink($p);
        }
    }

    public function testLoadThrowsOnReadFailure(): void
    {
        if (!in_array('failread', stream_get_wrappers(), true)) {
            stream_wrapper_register('failread', FailReadStreamWrapper::class);
        }

        set_error_handler(static fn (int $errno, string $errstr): bool => true);
        try {
            $store = new FileSemConvStore('failread://semconv');
            $this->expectException(\RuntimeException::class);
            $store->load();
        } finally {
            restore_error_handler();
            stream_wrapper_unregister('failread');
        }
    }

    public function testSaveCreatesDirectoryAndWrites(): void
    {
        $dir = sys_get_temp_dir() . '/semconv_' . uniqid() . '/sub';
        $path = $dir . '/registry.php';
        try {
            $store = new FileSemConvStore($path);
            $store->save('interface body');

            $this->assertSame('interface body', file_get_contents($path));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
            @rmdir(dirname($dir));
        }
    }

    public function testSaveThrowsWhenDirectoryUncreatable(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'blocker');
        $path = $file . '/sub/registry.php';
        set_error_handler(static fn (int $errno, string $errstr): bool => true);
        try {
            $store = new FileSemConvStore($path);
            $this->expectException(\RuntimeException::class);
            $store->save('x');
        } finally {
            restore_error_handler();
            unlink($file);
        }
    }

    public function testSaveThrowsOnWriteFailure(): void
    {
        $dir = sys_get_temp_dir() . '/semconvw_' . uniqid();
        mkdir($dir);
        set_error_handler(static fn (int $errno, string $errstr): bool => true);
        try {
            $store = new FileSemConvStore($dir);
            $this->expectException(\RuntimeException::class);
            $store->save('x');
        } finally {
            restore_error_handler();
            @rmdir($dir);
        }
    }
}

final class FailReadStreamWrapper
{
    public function url_stat(string $path, int $flags): array
    {
        return ['size' => 10, 'mode' => 0o100000];
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }

    public function stream_read(int $count): string
    {
        return '';
    }

    public function stream_eof(): bool
    {
        return true;
    }

    public function stream_stat(): array
    {
        return [];
    }
}
