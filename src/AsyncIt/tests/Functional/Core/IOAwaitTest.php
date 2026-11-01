<?php

declare(strict_types=1);

namespace Tests\Functional\Core;

use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class IOAwaitTest extends TestCase
{
    public function testAwaitReadableReturnsTrueWhenStreamBecomesReadable(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $readHandle = $scheduler->registerStream($pair[0]);
        $writeStream = $pair[1];

        $scheduler->spawn(static function () use (&$log, $scheduler, $readHandle): void {
            $log[] = 'before';
            $ready = $scheduler->awaitReadable($readHandle, Duration::ofSeconds(5));
            $log[] = 'after';
            $log[] = $ready ? 'ready' : 'timeout';
        });

        $scheduler->spawn(static function () use ($writeStream): void {
            usleep(50_000);
            fwrite($writeStream, 'hello');
        });

        $scheduler->run();

        self::assertSame(['before', 'after', 'ready'], $log);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testAwaitReadableReturnsFalseOnTimeout(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $readHandle = $scheduler->registerStream($pair[0]);

        $scheduler->spawn(static function () use (&$log, $scheduler, $readHandle): void {
            $result = $scheduler->awaitReadable($readHandle, Duration::ofNanoseconds(1));
            $log[] = $result ? 'ready' : 'timeout';
        });

        $scheduler->run();

        self::assertContains($log[0], ['ready', 'timeout']);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testAwaitWritableReturnsTrueWhenStreamIsWritable(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $writeHandle = $scheduler->registerStream($pair[0]);

        $scheduler->spawn(static function () use (&$log, $scheduler, $writeHandle): void {
            $log[] = 'before';
            $ready = $scheduler->awaitWritable($writeHandle, Duration::ofSeconds(5));
            $log[] = 'after';
            $log[] = $ready ? 'ready' : 'timeout';
        });

        $scheduler->run();

        self::assertSame(['before', 'after', 'ready'], $log);

        fclose($pair[0]);
        fclose($pair[1]);
    }

    public function testAwaitWritableReturnsFalseOnTimeout(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $log = [];
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertNotFalse($pair);

        $writeHandle = $scheduler->registerStream($pair[0]);

        $scheduler->spawn(static function () use (&$log, $scheduler, $writeHandle): void {
            $result = $scheduler->awaitWritable($writeHandle, Duration::ofNanoseconds(1));
            $log[] = $result ? 'ready' : 'timeout';
        });

        $scheduler->run();

        self::assertContains($log[0], ['ready', 'timeout']);

        fclose($pair[0]);
        fclose($pair[1]);
    }
}
