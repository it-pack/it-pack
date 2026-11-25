<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Psr\Event;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Psr\Event\OccurrenceEventWrapper;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OccurrenceEventWrapper::class)]
final class OccurrenceEventWrapperTest extends Unit
{
    public function testWrapReturnsEvent(): void
    {
        $wrapper = new OccurrenceEventWrapper();
        $occurrence = $this->createMock(OccurrenceInterface::class);

        $event = $wrapper->wrap($occurrence);

        self::assertSame($occurrence, $event->occurrence);
    }
}
