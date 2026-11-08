<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Execution;

use ItPack\RunIt\SDK\Attribute\ExecutedAs;
use ItPack\RunIt\SDK\Execution\MetadataExecutionDefinitionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataExecutionDefinitionFactory::class)]
final class MetadataExecutionDefinitionFactoryTest extends TestCase
{
    public function testCreateUsesMetadataNameAndDefaultKind(): void
    {
        $factory = new MetadataExecutionDefinitionFactory();

        $metadata = new ExecutedAs('foo');

        $definition = $factory->create($metadata);

        self::assertSame('foo', $definition->name()->toString());
        self::assertSame('operation', $definition->kind()->toString());
    }

    public function testCreatePreservesExplicitKind(): void
    {
        $factory = new MetadataExecutionDefinitionFactory();

        $metadata = new ExecutedAs('foo', 'task');

        $definition = $factory->create($metadata);

        self::assertSame('foo', $definition->name()->toString());
        self::assertSame('task', $definition->kind()->toString());
    }
}
