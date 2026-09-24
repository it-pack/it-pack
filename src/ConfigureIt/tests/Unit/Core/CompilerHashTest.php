<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSourceInterface;
use ItPack\ConfigureIt\Contract\DeclaresScalarTypesInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ValidationResultInterface;
use ItPack\ConfigureIt\Core\ConfigurationCompiler;
use ItPack\ConfigureIt\Core\DefaultsProcessor;
use ItPack\ConfigureIt\Execution\ExecutionContext;
use ItPack\ConfigureIt\ValueObject\ConfigurationResource;
use ItPack\ConfigureIt\ValueObject\Format;
use ItPack\ConfigureIt\ValueObject\ValidationResult;
use RuntimeException;
use Tests\Support\Extension\VirtualFileSystem;

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
    use VirtualFileSystem;

    private ConfigurationCompiler $compiler;

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
        $dir = $this->vfsPath('/cfg-hash-' . uniqid());
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

    /**
     * A schema that also declares scalar types must fold those declarations into
     * the key: tightening a type changes the resolution result (typed snapshot),
     * so it must invalidate the artifact.
     */
    public function testDeclaredScalarTypesArePartOfTheKey(): void
    {
        $sources = [$this->sourceWith('/nonexistent-cfg/opaque.php')];

        $withInt = $this->compiler->hash($sources, $this->typedSchema(['port' => 'int']), [], [], [], null);
        $withString = $this->compiler->hash($sources, $this->typedSchema(['port' => 'string']), [], [], [], null);
        $withNone = $this->compiler->hash($sources, $this->typedSchema([]), [], [], [], null);
        $plain = $this->compiler->hash($sources, $this->plainSchema(), [], [], [], null);
        $none = $this->compiler->hash($sources, null, [], [], [], null);

        verify($withInt)->notEquals($withString);
        verify($withInt)->notEquals($withNone);
        verify($withNone)->notEquals($plain);
        verify($plain)->notEquals($none);

        // Stable for identical declarations.
        verify($withInt)->equals(
            $this->compiler->hash($sources, $this->typedSchema(['port' => 'int']), [], [], [], null),
        );
    }

    public function testExecutionContextEnvironmentAndAttributesArePartOfTheKey(): void
    {
        $sources = [$this->sourceWith('/nonexistent-cfg/opaque.php')];

        $none = $this->compiler->hash($sources, null, [], [], [], null);
        $prod = $this->compiler->hash($sources, null, [], [], [], new ExecutionContext(environment: 'prod'));
        $dev = $this->compiler->hash($sources, null, [], [], [], new ExecutionContext(environment: 'dev'));
        $prodTenant = $this->compiler->hash(
            $sources,
            null,
            [],
            [],
            [],
            new ExecutionContext(environment: 'prod', attributes: ['tenant' => 'acme']),
        );

        verify($none)->notEquals($prod);
        verify($prod)->notEquals($dev);
        verify($prod)->notEquals($prodTenant);

        verify($prod)->equals(
            $this->compiler->hash($sources, null, [], [], [], new ExecutionContext(environment: 'prod')),
        );
    }

    public function testPipelineComponentClassesArePartOfTheKey(): void
    {
        $sources = [$this->sourceWith('/nonexistent-cfg/opaque.php')];

        $bare = $this->compiler->hash($sources, null, [], [], [], null);
        $withReader = $this->compiler->hash($sources, null, [], [new FileReader()], [], null);
        $withLoader = $this->compiler->hash($sources, null, [], [], [new PhpLoader()], null);
        $withProcessor = $this->compiler->hash($sources, null, [new DefaultsProcessor([])], [], [], null);

        verify(count(array_unique([$bare, $withReader, $withLoader, $withProcessor])))->equals(4);
    }

    public function testDiscoveryOrderDoesNotChangeTheKey(): void
    {
        $forward = [$this->sourceWith('/nonexistent-cfg/a.php'), $this->sourceWith('/nonexistent-cfg/b.php')];
        $reverse = [$this->sourceWith('/nonexistent-cfg/b.php'), $this->sourceWith('/nonexistent-cfg/a.php')];

        verify($this->compiler->hash($forward, null, [], [], [], null))
            ->equals($this->compiler->hash($reverse, null, [], [], [], null))
        ;
    }

    public function testEmptySourceSetStillProducesAStableKey(): void
    {
        $first = $this->compiler->hash([], null, [], [], [], null);
        $second = $this->compiler->hash([], null, [], [], [], null);

        verify($first)->equals($second);
        verify(strlen($first))->equals(64);
    }

    protected function _before(): void
    {
        $this->compiler = new ConfigurationCompiler();
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

    /**
     * @param array<string, string> $types
     */
    private function typedSchema(array $types): ConfigurationSchemaInterface
    {
        return new class ($types) implements ConfigurationSchemaInterface, DeclaresScalarTypesInterface {
            /**
             * @param array<string, string> $types
             */
            public function __construct(private array $types)
            {
            }

            public function name(): string
            {
                return 'typed-stub-schema';
            }

            public function validate(ConfigurationGraphInterface $graph): ValidationResultInterface
            {
                return new ValidationResult(true);
            }

            /**
             * @return array<string, string>
             */
            public function scalarTypes(): array
            {
                return $this->types;
            }
        };
    }

    private function plainSchema(): ConfigurationSchemaInterface
    {
        return new class () implements ConfigurationSchemaInterface {
            public function name(): string
            {
                return 'plain-stub-schema';
            }

            public function validate(ConfigurationGraphInterface $graph): ValidationResultInterface
            {
                return new ValidationResult(true);
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
        throw new RuntimeException(
            'SpyStreamWrapper: resource was opened by hash_file() — F16 guard failed',
        );
    }
}
