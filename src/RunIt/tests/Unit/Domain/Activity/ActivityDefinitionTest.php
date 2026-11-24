<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activity;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ActivityDefinition::class)]
final class ActivityDefinitionTest extends Unit
{
    public function testCreateWithNameAndKind(): void
    {
        $definition = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io'),
        );

        self::assertSame('load', $definition->name()->toString());
        self::assertSame('io', $definition->kind()->toString());
        self::assertNull($definition->parentActivityId());
    }

    public function testCreateWithParentActivityId(): void
    {
        $parent = ActivityId::fromString('parent-1');
        $definition = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io'),
            $parent,
        );

        self::assertSame($parent, $definition->parentActivityId());
    }

    public function testNormalizeReturnsFields(): void
    {
        $definition = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io'),
            ActivityId::fromString('parent-1'),
        );

        $normalized = $definition->normalize();

        self::assertSame('load', $normalized['name']);
        self::assertSame('io', $normalized['kind']);
        self::assertSame('parent-1', $normalized['parent_activity_id']);
    }

    public function testNormalizeWithNullParentId(): void
    {
        $definition = ActivityDefinition::create(
            ActivityName::fromString('load'),
            ActivityKind::fromString('io'),
        );

        $normalized = $definition->normalize();

        self::assertNull($normalized['parent_activity_id']);
    }
}
