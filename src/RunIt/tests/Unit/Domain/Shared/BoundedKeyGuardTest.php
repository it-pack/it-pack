<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Shared\BoundedKeyGuard;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(BoundedKeyGuard::class)]
final class BoundedKeyGuardTest extends Unit
{
    public function testRejectsEmptyKey(): void
    {
        $guard = new BoundedKeyGuard(
            new SecurityLimits(),
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (int $length, int $maximum): object => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
        );

        $this->expectException(RunItExceptionInterface::class);
        $guard->assertValidKey('');
    }

    public function testRejectsTooLongKey(): void
    {
        $guard = new BoundedKeyGuard(
            new SecurityLimits(),
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (int $length, int $maximum): RunItExceptionInterface => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
        );

        $this->expectException(RunItExceptionInterface::class);
        $guard->assertValidKey(str_repeat('a', 10000));
    }

    public function testRejectsUnsupportedCharacters(): void
    {
        $guard = new BoundedKeyGuard(
            new SecurityLimits(),
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (int $length, int $maximum): object => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
        );

        $this->expectException(RunItExceptionInterface::class);
        $guard->assertValidKey('bad/key');
    }

    public function testAcceptsValidKey(): void
    {
        $guard = new BoundedKeyGuard(
            new SecurityLimits(),
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (int $length, int $maximum): object => new class () extends RuntimeException implements RunItExceptionInterface {},
            fn (): object => new class () extends RuntimeException implements RunItExceptionInterface {},
        );

        $guard->assertValidKey('valid-key.name_1');
    }
}
