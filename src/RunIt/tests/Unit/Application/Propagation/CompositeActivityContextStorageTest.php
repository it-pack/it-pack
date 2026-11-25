<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Propagation\CompositeActivityContextStorage;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\RuntimeContextDetectorInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompositeActivityContextStorage::class)]
final class CompositeActivityContextStorageTest extends Unit
{
    public function testIsConstructedWithStoresAndDetector(): void
    {
        $storage = new CompositeActivityContextStorage(
            $this->createMock(ContextStorageInterface::class),
            $this->createMock(ContextStorageInterface::class),
            $this->createMock(ContextStorageInterface::class),
            $this->createMock(RuntimeContextDetectorInterface::class),
        );

        self::assertInstanceOf(CompositeActivityContextStorage::class, $storage);
    }
}
