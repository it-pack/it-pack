<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Domain\Context\ExecutionContext;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ExecutionDefinition::class)]
final class ExecutionDefinitionTest extends Unit
{
    public function testCreateWithNameAndKind(): void
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
        );

        self::assertSame('op', $definition->name()->toString());
        self::assertSame('task', $definition->kind()->toString());
        self::assertNull($definition->parentExecutionId());
        self::assertSame([], $definition->linkedExecutionIds());
        self::assertInstanceOf(ExecutionContext::class, $definition->context());
    }

    public function testCreateWithParentAndLinkedIds(): void
    {
        $parent = ExecutionId::fromString('parent-1');
        $linked = [ExecutionId::fromString('linked-1')];

        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
            $parent,
            $linked,
        );

        self::assertSame($parent, $definition->parentExecutionId());
        self::assertSame(
            ['linked-1'],
            array_map(
                static fn (ExecutionIdInterface $id): string => $id->toString(),
                $definition->linkedExecutionIds()
            )
        );
    }

    public function testNormalizeReturnsFields(): void
    {
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task'),
            ExecutionId::fromString('parent-1'),
            [ExecutionId::fromString('linked-1')],
        );

        $normalized = $definition->normalize();

        self::assertSame('op', $normalized['name']);
        self::assertSame('task', $normalized['kind']);
        self::assertSame('parent-1', $normalized['parent_execution_id']);
        self::assertSame(['linked-1'], $normalized['linked_execution_ids']);
        self::assertArrayHasKey('context', $normalized);
    }
}
