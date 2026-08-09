<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Exception;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Contract\Exception\UnsupportedOperationExceptionInterface;

final class ExceptionMarkerContractTest extends Unit
{
    /**
     * @param class-string $candidate
     */
    private function extendsInterface(string $candidate): bool
    {
        return in_array(
            RunItExceptionInterface::class,
            class_implements($candidate) ?: [],
            true
        );
    }

    public function testCategoryMarkersShareTheRootMarker(): void
    {
        self::assertTrue(
            $this->extendsInterface(
                InvalidValueExceptionInterface::class
            )
        );
        self::assertTrue(
            $this->extendsInterface(
                LifecycleExceptionInterface::class
            )
        );
        self::assertTrue(
            $this->extendsInterface(
                UnsupportedOperationExceptionInterface::class
            )
        );
    }

    public function testConcreteExceptionCanImplementMultipleMarkers(): void
    {
        $exception = new class () extends InvalidArgumentException implements InvalidValueExceptionInterface {};

        self::assertInstanceOf(
            InvalidValueExceptionInterface::class,
            $exception
        );
        self::assertInstanceOf(
            InvalidArgumentException::class,
            $exception
        );
    }
}
