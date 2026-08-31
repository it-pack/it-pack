<?php

declare(strict_types=1);

namespace Tests\Unit;

use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Filepath;
use ItPack\HashIt\HasherInterface;
use ItPack\HashIt\HashProgress;
use ItPack\HashItAsync\ConcurrentFileHasher;
use PHPUnit\Framework\TestCase;

/**
 * Proves the bridge returns the same digest map as a sequential HashIt hashFiles,
 * preserves path order, surfaces HashIt's exceptions unchanged, and reports
 * progress per file.
 *
 * @covers \ItPack\HashItAsync\ConcurrentFileHasher
 */
final class ConcurrentFileHasherTest extends TestCase
{
    private function runtime(): AsyncRuntime
    {
        return AsyncRuntime::discover();
    }

    private function hasher(): HasherInterface
    {
        return NativeHashIt::create();
    }

    private function tempFile(string $content): Filepath
    {
        $path = tempnam(sys_get_temp_dir(), 'hit');
        file_put_contents($path, $content);

        return new Filepath($path);
    }

    public function testYieldsSameMapAndOrderAsSequentialHashFiles(): void
    {
        $paths = [
            $this->tempFile('alpha-content'),
            $this->tempFile('bravo-content-longer'),
            $this->tempFile('charlie'),
        ];

        $hasher = $this->hasher();
        $runtime = $this->runtime();

        $concurrent = new ConcurrentFileHasher()->hashFiles($paths, $hasher, $runtime);
        $sequential = $hasher->hashFiles($paths);

        self::assertSame(
            array_values(array_map(static fn ($h) => $h->value(), $sequential)),
            array_values(array_map(static fn ($h) => $h->value(), $concurrent)),
        );
        self::assertSame(
            array_map(static fn (Filepath $p) => (string) $p, $paths),
            array_keys($concurrent),
        );
    }

    public function testEmptyInputYieldsEmptyMap(): void
    {
        $result = new ConcurrentFileHasher()->hashFiles([], $this->hasher(), $this->runtime());

        self::assertSame([], $result);
    }

    public function testMissingFileSurfacesSameRuntimeExceptionAsHashIt(): void
    {
        $missing = new Filepath('/this/path/does-not-exist-' . uniqid());

        $this->expectException(\RuntimeException::class);

        new ConcurrentFileHasher()->hashFiles([$missing], $this->hasher(), $this->runtime());
    }

    public function testReportsProgressPerFileWithRunningTotal(): void
    {
        $paths = [
            $this->tempFile('aaaa'),
            $this->tempFile('bbbbbb'),
            $this->tempFile('cccccccccc'),
        ];
        $total = 4 + 6 + 10;

        $seen = [];
        $progress = HashProgress::fromCallable(
            static function (int $processed, ?int $totalBytes) use (&$seen): void {
                $seen[] = [$processed, $totalBytes];
            },
        );

        new ConcurrentFileHasher()->hashFiles($paths, $this->hasher(), $this->runtime(), $progress);

        self::assertSame(
            [[4, $total], [10, $total], [20, $total]],
            $seen,
        );
    }
}
