<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use ItPack\DeriveIt\Cli\FileTargetResolver;
use PHPUnit\Framework\TestCase;

/**
 * Direct unit coverage of the tokenizer-based file→type resolution that
 * {@see \ItPack\DeriveIt\Cli\DeriveCommand} delegates to (R4-9 / F-8).
 */
final class FileTargetResolverTest extends TestCase
{
    private FileTargetResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new FileTargetResolver();
    }

    public function testResolvesNamespacedTypeFromFile(): void
    {
        $file = __DIR__ . '/Fixtures/CliSampleFile.php';

        self::assertSame('Tests\\Unit\\Cli\\Fixtures\\CliSampleFile', $this->resolver->resolve($file));
    }

    public function testResolvesGlobalClassFromFile(): void
    {
        $file = __DIR__ . '/Fixtures/CliSampleGlobal.php';

        self::assertSame('CliSampleGlobal', $this->resolver->resolve($file));
    }

    public function testThrowsWhenFileDeclaresNoType(): void
    {
        $file = __DIR__ . '/Fixtures/CliSampleNoClass.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no class/interface/trait/enum declaration found');

        $this->resolver->resolve($file);
    }
}
