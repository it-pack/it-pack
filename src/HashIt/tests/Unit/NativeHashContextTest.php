<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use ItPack\HashIt\Adapter\NativeContextFactory;

class NativeHashContextTest extends Unit
{
    public function testUpdateThenFinalizeMatchesNativeHash(): void
    {
        $factory = new NativeContextFactory();

        $ctx = $factory->build('sha256');
        $ctx->update('abc');
        verify($ctx->finalize(false))->equals(hash('sha256', 'abc'));

        $binary = $factory->build('sha256');
        $binary->update('abc');
        verify(strlen($binary->finalize(true)))->equals(32);
    }

    public function testIncrementalUpdateMatchesSingleUpdate(): void
    {
        $factory = new NativeContextFactory();

        $ctx = $factory->build('sha256');
        $ctx->update('ab');
        $ctx->update('c');

        verify($ctx->finalize(false))->equals(hash('sha256', 'abc'));
    }

    public function testMultipleAlgorithms(): void
    {
        $factory = new NativeContextFactory();

        $md5 = $factory->build('md5');
        $md5->update('abc');
        verify($md5->finalize(false))->equals(md5('abc'));
    }

    public function testFactoryNormalizesUnknownAlgorithmToPackageException(): void
    {
        $factory = new NativeContextFactory();

        $thrown = null;
        try {
            $factory->build('not-an-algorithm');
        } catch (\InvalidArgumentException $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(\InvalidArgumentException::class, $thrown);
        verify(str_contains($thrown->getMessage(), 'not-an-algorithm'))->true();
        self::assertInstanceOf(\ValueError::class, $thrown->getPrevious());
    }
}
