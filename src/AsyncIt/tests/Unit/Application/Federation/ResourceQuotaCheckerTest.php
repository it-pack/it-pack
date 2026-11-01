<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use InvalidArgumentException;
use ItPack\AsyncIt\Application\Federation\ResourceQuotaChecker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\ResourceQuotaChecker
 */
final class ResourceQuotaCheckerTest extends TestCase
{
    public function testAllowsDispatchBelowQuota(): void
    {
        $checker = new ResourceQuotaChecker(2);
        $task = new ResourceQuotaFakeTask('t1');

        $result = $checker->process($task, fn (): string => 'ok');

        self::assertSame('ok', $result);
    }

    public function testRejectsWhenQuotaIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ResourceQuotaChecker(0);
    }

    public function testSlotReleasedAfterDispatch(): void
    {
        $checker = new ResourceQuotaChecker(1);
        $task1 = new ResourceQuotaFakeTask('t1');
        $task2 = new ResourceQuotaFakeTask('t2');

        self::assertSame('first', $checker->process($task1, fn (): string => 'first'));
        self::assertSame('second', $checker->process($task2, fn (): string => 'second'));
    }

    public function testExceptionInNextStillReleasesSlot(): void
    {
        $checker = new ResourceQuotaChecker(1);
        $task1 = new ResourceQuotaFakeTask('t1');
        $task2 = new ResourceQuotaFakeTask('t2');

        $this->expectException(RuntimeException::class);
        $checker->process($task1, function (): string {
            throw new RuntimeException('boom');
        });

        self::assertSame('ok', $checker->process($task2, fn (): string => 'ok'));
    }
}
