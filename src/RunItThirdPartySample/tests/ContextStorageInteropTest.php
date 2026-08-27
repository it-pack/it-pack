<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Tests;

use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunItThirdPartySample\Context\StaticStackContextStorage;
use ItPack\RunItThirdPartySample\Tests\Support\RunItFactory;
use PHPUnit\Framework\TestCase;

/**
 * Proves the sample storage works as the live ambient scope storage of a real
 * execution, not just in isolation: the running operation sees exactly the attached
 * scope, and the scope is detached once the execution completes.
 */
final class ContextStorageInteropTest extends TestCase
{
    public function testStaticStackContextStorageServesAsLiveAmbientStorage(): void
    {
        $storage = new StaticStackContextStorage();
        $publisher = new InMemoryArtifactPublisher();

        $runIt = RunItFactory::create($publisher, $storage);
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('sample.interop'),
            ExecutionKind::fromString('cli.command'),
        );

        $runIt->run($definition, static function (ExecutionScopeInterface $scope) use ($storage): void {
            self::assertSame($scope, $storage->current());
        });

        self::assertNull($storage->current());
        self::assertNotEmpty($publisher->published());
    }
}
