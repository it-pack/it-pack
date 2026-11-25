<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activity;

use Codeception\Attribute\DataProvider;
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

    #[DataProvider('transitions')]
    public function testCanTransitionTo(ActivityStatus $from, ActivityStatus $to, bool $expected): void
    {
        self::assertSame($expected, $from->canTransitionTo($to));
    }

    /**
     * @return array<string, array{ActivityStatus, ActivityStatus, bool}>
     */
    public static function transitions(): array
    {
        return [
            'terminal cannot transition' => [ActivityStatus::Succeeded, ActivityStatus::Running, false],
            'created to running' => [ActivityStatus::Created, ActivityStatus::Running, true],
            'created to cancelled' => [ActivityStatus::Created, ActivityStatus::Cancelled, true],
            'created to succeeded not allowed' => [ActivityStatus::Created, ActivityStatus::Succeeded, false],
            'running to succeeded' => [ActivityStatus::Running, ActivityStatus::Succeeded, true],
            'running to failed' => [ActivityStatus::Running, ActivityStatus::Failed, true],
            'running to cancelled' => [ActivityStatus::Running, ActivityStatus::Cancelled, true],
            'running to timed_out' => [ActivityStatus::Running, ActivityStatus::TimedOut, true],
            'running to created not allowed' => [ActivityStatus::Running, ActivityStatus::Created, false],
        ];
    }
}
