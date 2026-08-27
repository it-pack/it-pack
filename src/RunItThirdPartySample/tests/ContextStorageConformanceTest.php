<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Tests;

use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Conformance\Verifier\ContextStorageVerifier;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunItThirdPartySample\Context\StaticStackContextStorage;
use ItPack\RunItThirdPartySample\Tests\Support\RunItFactory;
use PHPUnit\Framework\TestCase;

/**
 * Drives the shipped {@see ContextStorageVerifier} against the sample storage.
 *
 * The verifier needs an {@see ExecutionScopeInterface}; a third party obtains a real
 * one by running a real execution through the public SDK builder, then hands it to
 * the verifier together with their own storage implementation.
 */
final class ContextStorageConformanceTest extends TestCase
{
    public function testStaticStackContextStoragePassesContextStorageConformance(): void
    {
        $capturedScope = null;

        $runIt = RunItFactory::create(new InMemoryArtifactPublisher());
        $definition = ExecutionDefinition::create(
            ExecutionName::fromString('sample.context'),
            ExecutionKind::fromString('cli.command'),
        );

        $runIt->run($definition, static function (ExecutionScopeInterface $scope) use (&$capturedScope): void {
            $capturedScope = $scope;
        });

        self::assertInstanceOf(ExecutionScopeInterface::class, $capturedScope);

        $storage = new StaticStackContextStorage();
        $result = (new ContextStorageVerifier())->verify($storage, $capturedScope);

        self::assertTrue($result->isConformant(), $result->summary());
    }
}
