<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\Contract\CoroutineIdProviderInterface;
use ItPack\RunIt\Adapter\Swoole\CoroutineActivityContextStorage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CoroutineActivityContextStorage::class)]
final class CoroutineActivityContextStorageTest extends Unit
{
    public function testIsConstructedWithProvider(): void
    {
        $storage = new CoroutineActivityContextStorage(
            $this->createMock(CoroutineIdProviderInterface::class),
        );

        self::assertInstanceOf(CoroutineActivityContextStorage::class, $storage);
    }
}
