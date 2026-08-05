<?php

declare(strict_types=1);

namespace Tests\Unit\Contract\Exception;

use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\LifecycleExceptionInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Contract\Exception\UnsupportedOperationExceptionInterface;

final class ExceptionMarkerContractTest extends \Codeception\Test\Unit
{
    /**
     * @param class-string $candidate
     * @param class-string $parent
     */
    private function extendsInterface(string $candidate, string $parent): bool
    {
        $implements = class_implements($candidate) ? class_implements($candidate) : [];

        return in_array($parent, $implements, true);
    }

    public function testCategoryMarkersShareTheRootMarker(): void
    {
        self::assertTrue($this->extendsInterface(InvalidValueExceptionInterface::class, RunItExceptionInterface::class));
        self::assertTrue($this->extendsInterface(LifecycleExceptionInterface::class, RunItExceptionInterface::class));
        self::assertTrue($this->extendsInterface(UnsupportedOperationExceptionInterface::class, RunItExceptionInterface::class));
    }

    public function testConcreteExceptionCanImplementMultipleMarkers(): void
    {
        $exception = new class () extends \InvalidArgumentException implements InvalidValueExceptionInterface {
        };

        self::assertInstanceOf(InvalidValueExceptionInterface::class, $exception);
        self::assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
