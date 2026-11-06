<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use ItPack\RunIt\Contract\Domain\ExecutionKindInterface;
use ItPack\RunIt\Contract\Domain\ExecutionNameInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionDefinitionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutionDefinitionFactory::class)]
class ExecutionDefinitionFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new ExecutionDefinitionFactory();

        $this->assertInstanceOf(
            ExecutionDefinition::class,
            $factory->create(
                $this->createMock(ExecutionNameInterface::class),
                $this->createMock(ExecutionKindInterface::class),
            )
        );
    }
}
