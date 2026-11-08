<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionNamedType;
use ReflectionObject;
use RuntimeException;
use Throwable;

#[CoversClass(DefaultThrowableMapper::class)]
#[UsesClass(ThrowableDescription::class)]
final class DefaultThrowableMapperTest extends Unit
{
    private function chain(int $depth): RuntimeException
    {
        $throwable = new RuntimeException('root', 1);

        for ($index = 0; $index < $depth; ++$index) {
            $throwable = new RuntimeException('level-' . $index, $index + 1, $throwable);
        }

        return $throwable;
    }

    public function testDescribeCapturesClassMessageAndCode(): void
    {
        $mapper = new DefaultThrowableMapper();
        $description = $mapper->describe(new RuntimeException('something failed', 42));

        self::assertInstanceOf(ThrowableDescriptionInterface::class, $description);
        self::assertSame(RuntimeException::class, $description->class());
        self::assertSame('something failed', $description->message());
        self::assertSame(42, $description->code());
    }

    public function testTraceIsExcludedByDefault(): void
    {
        $mapper = new DefaultThrowableMapper();
        $description = $mapper->describe(new RuntimeException('no trace'));

        self::assertNull($description->trace());
    }

    public function testTraceIsIncludedWhenEnabled(): void
    {
        $mapper = new DefaultThrowableMapper(includeTrace: true);
        $description = $mapper->describe(new RuntimeException('with trace'));

        self::assertIsString($description->trace());
        self::assertStringContainsString('#0', $description->trace());
    }

    public function testTraceIsTruncatedToConfiguredMaximum(): void
    {
        $mapper = new DefaultThrowableMapper(includeTrace: true, maxTraceLength: 10);
        $description = $mapper->describe(new RuntimeException('with trace'));

        self::assertNotNull($description->trace());
        self::assertLessThanOrEqual(10, strlen($description->trace()));
    }

    public function testMessageIsTruncatedToConfiguredMaximum(): void
    {
        $message = str_repeat('a', 2048);
        $mapper = new DefaultThrowableMapper(maxMessageLength: 64);

        $description = $mapper->describe(new RuntimeException($message));

        self::assertSame(64, strlen($description->message()));
    }

    public function testPreviousCausesAreCapturedRecursivelyUpToDepth(): void
    {
        $mapper = new DefaultThrowableMapper(maxCauseDepth: 3);
        $description = $mapper->describe($this->chain(6));

        $depth = 0;
        $current = $description;
        while ($current->previous() instanceof ThrowableDescriptionInterface) {
            $current = $current->previous();
            ++$depth;
        }

        self::assertSame(3, $depth);
    }

    public function testDescribeDoesNotRetainTheOriginalThrowable(): void
    {
        $mapper = new DefaultThrowableMapper(includeTrace: true);
        $description = $mapper->describe(new RuntimeException('boom', 9));

        $reflection = new ReflectionObject($description);
        foreach ($reflection->getProperties() as $property) {
            $type = $property->getType();
            if ($type instanceof ReflectionNamedType) {
                self::assertFalse(
                    is_a($type->getName(), Throwable::class, allow_string: true),
                    'Property ' . $property->getName() . ' must not retain a Throwable.',
                );
            }
        }

        self::assertNull($description->previous());
    }
}
