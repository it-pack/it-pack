<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Contract\PromiseInterface
 */
final class PromiseInterfaceTest extends TestCase
{
    public function testIsDoneReturnsTrueAfterResolution(): void
    {
        $promise = new ResolvedPromise('result');

        self::assertTrue($promise->isDone());
        self::assertSame('result', $promise->get());
    }

    public function testGetRethrowsOriginalThrowable(): void
    {
        $original = new RuntimeException('boom');
        $promise = new RejectedPromise($original);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $promise->get();
    }

    public function testWaitReturnsTrueWhenAlreadyDone(): void
    {
        $promise = new ResolvedPromise('result');

        self::assertTrue($promise->wait(Duration::ofSeconds(1)));
    }
}
