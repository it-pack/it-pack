<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Behavior\Domain\BoundedPrimitiveValueWalkerInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Shared\BoundedPrimitiveValueWalker;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use stdClass;

#[CoversClass(BoundedPrimitiveValueWalker::class)]
final class BoundedPrimitiveValueWalkerTest extends Unit
{
    /**
     * @throws RunItExceptionInterface
     */
    public function testValidScalarAndNullPass(): void
    {
        $walker = $this->walker(
            SecurityLimits::default()
            ->withMaxAttributeValueBytes(10)
            ->withMaxAttributeDepth(2)
        );
        $walker->assertSupported('hello');
        $walker->assertSupported(42);
        $walker->assertSupported(null);
    }

    public function testStringTooLongReports(): void
    {
        $walker = $this->walker(
            SecurityLimits::default()
            ->withMaxAttributeValueBytes(10)
            ->withMaxAttributeDepth(2)
        );
        $this->expectException(RunItExceptionInterface::class);
        $walker->assertSupported(str_repeat('x', 11));
    }

    public function testNestingTooDeepReports(): void
    {
        $walker = $this->walker(
            SecurityLimits::default()
            ->withMaxAttributeValueBytes(10)
            ->withMaxAttributeDepth(2)
        );
        $this->expectException(RunItExceptionInterface::class);
        $walker->assertSupported([['a']]);
    }

    public function testUnsupportedValueReports(): void
    {
        $walker = $this->walker(
            SecurityLimits::default()
                ->withMaxAttributeValueBytes(10)
                ->withMaxAttributeDepth(2)
        );
        $this->expectException(RunItExceptionInterface::class);
        $walker->assertSupported(new stdClass());
    }

    public function testOversizedArrayReports(): void
    {
        $limits = SecurityLimits::default()
            ->withMaxAttributeCount(2)
        ;
        $walker = $this->walker(
            $limits
        );
        $this->expectException(RunItExceptionInterface::class);
        $walker->assertSupported(['a', 'b', 'c']);
    }

    private function walker(SecurityLimits $limits): BoundedPrimitiveValueWalkerInterface
    {
        return new BoundedPrimitiveValueWalker(
            $limits,
            fn (int $length, int $maximum): RunItExceptionInterface => $this->createRunItException(),
            fn (int $depth, int $maximum): RunItExceptionInterface => $this->createRunItException(),
            fn (): RunItExceptionInterface => $this->createRunItException(),
            fn (int $count, int $maximum): RunItExceptionInterface => $this->createRunItException(),
            true,
        );
    }

    private function createRunItException(): RunItExceptionInterface
    {
        return new class () extends RuntimeException implements RunItExceptionInterface {};
    }
}
