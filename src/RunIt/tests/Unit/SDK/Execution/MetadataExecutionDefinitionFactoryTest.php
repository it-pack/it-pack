<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Execution;

use ItPack\RunIt\Adapter\Common\InMemorySequenceAllocator;
use ItPack\RunIt\Adapter\Common\JsonSnapshotSerializer;
use ItPack\RunIt\Adapter\Common\RandomIdentifierGenerator;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\Attribute\ExecutedAs;
use ItPack\RunIt\SDK\Attribute\ExecutedAsInterface;
use ItPack\RunIt\SDK\Builder\RunItBuilder;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableInterface;
use ItPack\RunIt\SDK\DefaultRunIt;
use ItPack\RunIt\SDK\Exception\MissingExecutionDefinitionException;
use ItPack\RunIt\SDK\Execution\MetadataExecutionDefinitionFactory;
use ItPack\RunIt\SDK\RunItInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataExecutionDefinitionFactory::class)]
class MetadataExecutionDefinitionFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $factory = new MetadataExecutionDefinitionFactory();

        $metadata = new ExecutedAs(
            'foo'
        );

        $this->assertInstanceOf(
            ExecutionDefinition::class,
            $factory->create($metadata)
        );
    }
}
