<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Context;

use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\AsyncIt\Application\Context\MemoryContextStorage;
use ItPack\AsyncIt\Domain\ContextToken;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Application\Context\MemoryContextStorage
 */
final class MemoryContextStorageTest extends TestCase
{
    public function testCurrentReturnsNullWhenNoScopeAttached(): void
    {
        $storage = new MemoryContextStorage();

        self::assertNull($storage->current());
    }

    public function testCurrentReturnsMostRecentlyAttachedScope(): void
    {
        $storage = new MemoryContextStorage();
        $scope1 = $this->createScope();
        $scope2 = $this->createScope();

        $storage->attach($scope1);
        $storage->attach($scope2);

        self::assertSame($scope2, $storage->current());
    }

    public function testDetachRestoresPreviousScope(): void
    {
        $storage = new MemoryContextStorage();
        $scope1 = $this->createScope();
        $scope2 = $this->createScope();

        $storage->attach($scope1);
        $token2 = $storage->attach($scope2);
        self::assertSame($scope2, $storage->current());

        $storage->detach($token2);

        self::assertSame($scope1, $storage->current());
    }

    public function testDetachOutOfOrderThrowsLogicException(): void
    {
        $storage = new MemoryContextStorage();
        $scope = $this->createScope();
        $token = $storage->attach($scope);
        $storage->attach($this->createScope());

        $this->expectException(LogicException::class);
        $storage->detach($token);
    }

    public function testDetachOnEmptyStackThrowsLogicException(): void
    {
        $storage = new MemoryContextStorage();

        $this->expectException(LogicException::class);
        $storage->detach(new ContextToken(1));
    }

    private function createScope(): ExecutionScopeInterface
    {
        return $this->createMock(ExecutionScopeInterface::class);
    }
}
