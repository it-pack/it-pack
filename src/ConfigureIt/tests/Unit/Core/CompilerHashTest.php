<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Core\ConfigurationCompiler;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;

/**
 * F16 — {@see ConfigurationCompiler::hash()} must treat non-file resources
 * (vault://, s3://, https://, …) as opaque version tokens and never open them
 * via hash_file() (which would transparently fetch them through a stream
 * wrapper — an SSRF-shaped side effect).
 *
 * @covers \ItPack\ConfigureIt\Core\ConfigurationCompiler::hash
 */
final class CompilerHashTest extends Unit
{
    private ConfigurationCompiler $compiler;

    protected function _before(): void
    {
        $this->compiler = new ConfigurationCompiler();
    }

    /**
     * The spy wrapper throws if anything tries to *open* the resource. With the
     * is_file() guard in place, hash_file() is never reached for a non-file uri,
     * so no open happens and the uri is folded in verbatim as the token.
     */
    public function testNonFileUriIsNotOpenedAndUsedAsVersionToken(): void
    {
        $this->registerSpyWrapper();

        try {
            $key = $this->compiler->hash(
                [$this->sourceWith('vault://secret/db')],
                null,
                [],
                [],
                [],
                null,
            );

            // A different non-file uri must yield a different key, proving the
            // uri itself is part of the version token (not a fetched hash).
            $otherKey = $this->compiler->hash(
                [$this->sourceWith('vault://secret/db2')],
                null,
                [],
                [],
                [],
                null,
            );

            verify($key)->notEmpty();
            verify($key)->notEquals($otherKey);
        } finally {
            $this->unregisterSpyWrapper();
        }
    }

    /**
     * Regression: a real file's key is content-sensitive (edits invalidate) and
     * stable when the content is unchanged.
     */
    public function testFileHashIsContentSensitiveAndStable(): void
    {
        $dir = sys_get_temp_dir() . '/cfg-hash-' . uniqid();
        mkdir($dir);
        $file = $dir . '/config.php';

        try {
            file_put_contents($file, '<?php return ["a" => 1];');

            $source = [$this->sourceWith($file)];

            $first = $this->compiler->hash($source, null, [], [], [], null);
            $second = $this->compiler->hash($source, null, [], [], [], null);
            verify($first)->equals($second);

            file_put_contents($file, '<?php return ["a" => 2];');
            $changed = $this->compiler->hash($source, null, [], [], [], null);
            verify($changed)->notEquals($first);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }

            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    private function sourceWith(string $uri): ConfigurationSourceInterface
    {
        return new class ($uri) implements ConfigurationSourceInterface {
            public function __construct(private string $uri)
            {
            }

            public function discover(): iterable
            {
                return [new ConfigurationResource($this->uri, Format::PHP)];
            }
        };
    }

    private function registerSpyWrapper(): void
    {
        if (!in_array('vault', stream_get_wrappers(), true)) {
            stream_wrapper_register('vault', SpyStreamWrapper::class);
        }
    }

    private function unregisterSpyWrapper(): void
    {
        if (in_array('vault', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('vault');
        }
    }
}

/**
 * Stream wrapper that records whether a resource was *opened* during hashing.
 *
 * url_stat() reports "not found" so is_file() returns false (the guard skips
 * hash_file()). stream_open() throws — so if the guard were ever removed, the
 * test would blow up instead of silently fetching the remote resource.
 */
final class SpyStreamWrapper
{
    public function url_stat(string $path, int $flags): bool
    {
        return false;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        throw new \RuntimeException(
            'SpyStreamWrapper: resource was opened by hash_file() — F16 guard failed',
        );
    }
}
