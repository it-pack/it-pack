<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\SwooleCoroutineIdProvider;
use OpenSwoole\Coroutine as OpenSwooleCoroutine;

use function OpenSwoole\Coroutine\go as open_swoole_go;
use function OpenSwoole\Coroutine\run as open_swoole_run;

use PHPUnit\Framework\Attributes\CoversClass;
use Swoole\Coroutine as SwooleCoroutine;

use function Swoole\Coroutine\go as swoole_go;
use function Swoole\Coroutine\run as swoole_run;

#[CoversClass(SwooleCoroutineIdProvider::class)]
final class SwooleCoroutineIdProviderTest extends Unit
{
    public function testCurrentCoroutineIdReturnsInt(): void
    {
        $provider = new SwooleCoroutineIdProvider();

        self::assertIsInt($provider->currentCoroutineId());
    }

    /** @noinspection PhpUndefinedFunctionInspection */
    public function testCurrentCoroutineIdReturnsNonNegativeInt(): void
    {
        if (!$this->swooleInstalled() && !$this->openSwooleInstalled()) {
            $this->markTestSkipped();
        }

        $run = $this->swooleInstalled()
            ? swoole_run(...)
            : open_swoole_run(...);
        $go = $this->swooleInstalled()
            ? swoole_go(...)
            : open_swoole_go(...);

        $provider = new SwooleCoroutineIdProvider();
        $id = -1;

        $run(static function () use ($go, $provider, &$id): void {
            $go(static function () use ($provider, &$id): void {
                $id = $provider->currentCoroutineId();
            });
        });

        self::assertNotSame(-1, $id);
        self::assertTrue($id > 0);
    }

    /** @noinspection PhpUndefinedClassInspection */
    private function swooleInstalled(): bool
    {
        return
            function_exists('\Swoole\Coroutine\go')
            && function_exists('\Swoole\Coroutine\run')
            && method_exists(SwooleCoroutine::class, 'getCid');
    }

    /** @noinspection PhpUndefinedClassInspection */
    private function openSwooleInstalled(): bool
    {
        return
            function_exists('\OpenSwoole\Coroutine\go')
            && function_exists('\OpenSwoole\Coroutine\run')
            && method_exists(OpenSwooleCoroutine::class, 'getCid');
    }
}
