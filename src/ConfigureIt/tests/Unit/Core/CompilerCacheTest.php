<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Adapter\Processor;
use ItPack\ConfigureIt\Cache\FileSystemCache;
use ItPack\ConfigureIt\Contract\ConfigurationCacheInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSnapshotInterface;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ConfigurationSnapshotFactory;
use ItPack\ConfigureIt\Exception\TypeException;
use ItPack\ConfigureIt\ValueObject\ValidationResultFactory;
use Nette\Schema\Expect;
use RuntimeException;
use Tests\Support\Extension\VirtualFileSystem;

/**
 * M3 — Epic E7.1 (Compiler + cache) exit verification.
 *
 * @covers \ItPack\ConfigureIt\Core\ConfigurationCompiler
 * @covers \ItPack\ConfigureIt\Core\CachingResolver
 * @covers \ItPack\ConfigureIt\Cache\FileSystemCache
 * @covers \ItPack\ConfigureIt\Core\ConfigurationBuilder::withCache
 */
class CompilerCacheTest extends Unit
{
    use VirtualFileSystem;

    private string $dir;

    private string $cacheDir;

    public function testCacheHitServesEqualSnapshot(): void
    {
        $cache = new FileSystemCache($this->cacheDir, new ConfigurationSnapshotFactory());
        $resolver = $this->builder($cache)->build();

        $first = $resolver->resolve();
        verify($first->get('name'))->equals('acme');

        // Identical inputs => second resolve is served from the artifact and is equal.
        $second = $resolver->resolve();
        verify($second->all())->equals($first->all());

        // An artifact must have been written.
        verify(count(array_diff(scandir($this->cacheDir), ['.', '..'])))->greaterThan(0);
    }

    public function testHashChangeInvalidatesCache(): void
    {
        $cache = new FileSystemCache($this->cacheDir, new ConfigurationSnapshotFactory());
        $resolver = $this->builder($cache)->build();

        verify($resolver->resolve()->get('name'))->equals('acme');

        // Editing the source changes its mtime => different hash => miss + new value.
        $this->writeConfig(['name' => 'mutated', 'port' => 9090]);

        verify($resolver->resolve()->get('name'))->equals('mutated');
    }

    public function testFailureFallsBackToLiveResolve(): void
    {
        $failing = new class () implements ConfigurationCacheInterface {
            public function get(string $key): ?ConfigurationSnapshotInterface
            {
                return null;
            }

            public function set(string $key, ConfigurationSnapshotInterface $snapshot): void
            {
                throw new RuntimeException('disk full');
            }
        };

        $resolver = $this->builder($failing)->build();

        // A throwing writer must never break resolution.
        $snapshot = $resolver->resolve();
        verify($snapshot->get('name'))->equals('acme');
        verify($snapshot->get('port'))->equals(8080);
    }

    public function testCorruptArtifactFallsBackToLiveResolve(): void
    {
        $cache = new FileSystemCache($this->cacheDir, new ConfigurationSnapshotFactory());

        // Unknown key + non-array artifact both yield null (safe miss).
        verify($cache->get('missing'))->null();

        file_put_contents($this->cacheDir . '/corrupt.php', '<?php return "not-an-array";');
        verify($cache->get('corrupt'))->null();
    }

    public function testTypedSnapshotReconstructedFromCacheStillValidates(): void
    {
        $schema = new NativeConfigurationSchema(
            Expect::structure([
                'name' => Expect::string(),
                'port' => Expect::int(),
            ]),
            new Processor(),
            new ValidationResultFactory()
        );

        $cache = new FileSystemCache($this->cacheDir, new ConfigurationSnapshotFactory());
        $resolver = $this->builder($cache)->withSchema($schema)->build();

        // First resolve is a miss and mints a TypedSnapshot.
        $snapshot = $resolver->resolve();
        verify($snapshot->int('port'))->equals(8080);

        // Corrupt the cached artifact's port (string instead of int) and confirm
        // the next resolve — a hit — reconstructs + re-validates the typed snapshot.
        $artifact = $this->files('*.php')[0] ?? null;
        verify($artifact)->notNull();

        /** @var string $artifact */
        $data = require $artifact;
        $data['snapshot']['tree']['port'] = 'not-an-int';
        file_put_contents($artifact, '<?php return ' . var_export($data, true) . ';');

        $this->expectException(TypeException::class);
        $resolver->resolve();
    }

    protected function _before(): void
    {
        $this->dir = $this->vfsPath('/cfg-m3-' . uniqid());
        $this->cacheDir = $this->vfsPath('/cfg-m3-cache-' . uniqid());
        mkdir($this->dir, 0o700, true);

        $this->writeConfig(['name' => 'acme', 'port' => 8080]);
    }

    protected function _after(): void
    {
        foreach ([$this->dir, $this->cacheDir] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
                $path = $dir . '/' . $item;

                if (is_file($path)) {
                    unlink($path);
                }
            }

            rmdir($dir);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeConfig(array $data): void
    {
        file_put_contents($this->dir . '/config.php', '<?php return ' . var_export($data, true) . ';');
    }

    /**
     * @return list<string>
     */
    private function files(string $pattern): array
    {
        $entries = scandir($this->cacheDir);
        $matched = [];

        foreach (false === $entries ? [] : array_diff($entries, ['.', '..']) as $item) {
            if (fnmatch($pattern, $item)) {
                $matched[] = $this->cacheDir . '/' . $item;
            }
        }

        return $matched;
    }

    private function builder(?ConfigurationCacheInterface $cache = null): ConfigurationBuilder
    {
        $builder = \ItPack\ConfigureIt\Wiring\Configurations::builder()
            ->addReader(new FileReader())
            ->addLoader(new PhpLoader())
            ->addSource(new FileSource($this->dir))
        ;

        return null === $cache ? $builder : $builder->withCache($cache);
    }
}
