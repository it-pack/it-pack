<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\ConfigureIt\Cache\FileSystemCache;
use ItPack\ConfigureIt\Core\ConfigurationSnapshotFactory;
use ItPack\ConfigureIt\Core\TypedSnapshot;
use Tests\Support\Extension\VirtualFileSystem;

/**
 * Security-focused coverage for the compiled-artifact cache: directory
 * permissions, atomic artifact writes and cache-key validation (REVIEW NV2/NV17).
 *
 * @covers \ItPack\ConfigureIt\Cache\FileSystemCache
 */
final class FileSystemCacheTest extends Unit
{
    use VirtualFileSystem;

    private string $dir;

    public function testCreatedDirectoryIsNeverWorldAccessible(): void
    {
        new FileSystemCache($this->dir, new ConfigurationSnapshotFactory());

        verify(is_dir($this->dir))->true();
        // Artifacts hold decrypted configuration: no bits for "other" regardless of umask.
        verify(fileperms($this->dir) & 0o007)->equals(0);
    }

    public function testWritesAreAtomicWithoutTempResidue(): void
    {
        $cache = new FileSystemCache($this->dir, new ConfigurationSnapshotFactory());
        $snapshot = new ConfigurationSnapshotFactory()->build(['a' => 1]);

        $cache->set('artifact', $snapshot);
        $cache->set('typed', new TypedSnapshot($snapshot, ['a' => 'int']));

        verify($this->files('*.tmp*'))->equals([]);
        verify(count($this->files('*.php')))->equals(2);

        verify($cache->get('artifact')?->all())->equals(['a' => 1]);

        $typed = $cache->get('typed');
        verify($typed instanceof TypedSnapshot)->true();
        verify($typed?->int('a'))->equals(1);
    }

    /**
     * @dataProvider invalidKeys
     */
    public function testHostileKeysAreRejected(string $key): void
    {
        $cache = new FileSystemCache($this->dir, new ConfigurationSnapshotFactory());

        try {
            $cache->get($key);
            self::fail('Expected the invalid cache key to be rejected.');
        } catch (InvalidArgumentException $e) {
            verify(str_contains($e->getMessage(), 'Invalid cache key'))->true();
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public function invalidKeys(): array
    {
        return [
            'traversal' => ['../evil'],
            'nested path' => ['sub/dir'],
            'empty' => [''],
            'too long' => [str_repeat('a', 129)],
            'shell-ish' => ['a;b'],
        ];
    }

    public function testLegitimateKeysRoundTrip(): void
    {
        $cache = new FileSystemCache($this->dir, new ConfigurationSnapshotFactory());
        $snapshot = new ConfigurationSnapshotFactory()->build(['ok' => true]);

        foreach (['abc.def_G-h', str_repeat('0123456789abcdef', 8)] as $key) {
            $cache->set($key, $snapshot);

            verify($cache->get($key)?->all())->equals(['ok' => true]);
        }
    }

    protected function _before(): void
    {
        $this->dir = $this->vfsPath('/cfg-cache-sec-' . uniqid());
    }

    protected function _after(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }

        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            @unlink($this->dir . '/' . $item);
        }

        @rmdir($this->dir);
    }

    /**
     * @return list<string>
     */
    private function files(string $pattern): array
    {
        $entries = scandir($this->dir);
        $matched = [];

        foreach (false === $entries ? [] : array_diff($entries, ['.', '..']) as $item) {
            if (fnmatch($pattern, $item)) {
                $matched[] = $this->dir . '/' . $item;
            }
        }

        return $matched;
    }
}
