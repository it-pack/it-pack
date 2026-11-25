<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activity;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Activity\ActivityStatus;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ActivityStatus::class)]
final class ActivityStatusTest extends Unit
{
    public function testCasesHaveValues(): void
    {
        self::assertSame('created', ActivityStatus::Created->value);
        self::assertSame('running', ActivityStatus::Running->value);
        self::assertSame('succeeded', ActivityStatus::Succeeded->value);
        self::assertSame('failed', ActivityStatus::Failed->value);
        self::assertSame('cancelled', ActivityStatus::Cancelled->value);
        self::assertSame('timed_out', ActivityStatus::TimedOut->value);
    }

    public function testLabelAndTerminal(): void
    {
        self::assertSame('succeeded', ActivityStatus::Succeeded->label());
        self::assertTrue(ActivityStatus::Succeeded->isTerminal());
        self::assertFalse(ActivityStatus::Created->isTerminal());
    }
}
