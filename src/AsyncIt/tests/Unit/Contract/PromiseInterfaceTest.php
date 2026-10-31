<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

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

final readonly class ResolvedPromise implements PromiseInterface
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public function isDone(): bool
    {
        return true;
    }

    public function wait(?DurationInterface $timeout = null): bool
    {
        return true;
    }

    public function get(): mixed
    {
        return $this->value;
    }
}

final readonly class RejectedPromise implements PromiseInterface
{
    public function __construct(
        private Throwable $error,
    ) {
    }

    public function isDone(): bool
    {
        return true;
    }

    public function wait(?DurationInterface $timeout = null): bool
    {
        return true;
    }

    /**
     * @throws Throwable
     */
    public function get(): mixed
    {
        throw $this->error;
    }
}
