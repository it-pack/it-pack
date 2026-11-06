<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use ItPack\RunIt\Domain\Execution\ExecutionDefinitionFactory;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutionDefinitionFactory::class)]
final class ExecutionDefinitionFactoryTest extends TestCase
{
    public function testCreatePreservesNameAndKind(): void
    {
        $factory = new ExecutionDefinitionFactory();

        $name = ExecutionName::fromString('factory.op');
        $kind = ExecutionKind::fromString('operation');

        $definition = $factory->create($name, $kind);

        self::assertSame($name, $definition->name());
        self::assertSame($kind, $definition->kind());
        self::assertNull($definition->parentExecutionId());
        self::assertSame([], $definition->linkedExecutionIds());
    }

    public function testCreatePreservesOptionalFields(): void
    {
        $factory = new ExecutionDefinitionFactory();

        $parent = ExecutionId::fromString('parent-1');
        $linked = [ExecutionId::fromString('linked-1')];

        $definition = $factory->create(
            ExecutionName::fromString('child.op'),
            ExecutionKind::fromString('task'),
            $parent,
            $linked,
        );

        self::assertSame($parent, $definition->parentExecutionId());
        self::assertSame($linked, $definition->linkedExecutionIds());
    }
}
