<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Builder;

use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\SDK\RunItInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixture\RunItFactories;

#[CoversClass(className: RunItFactories::class)]
final class RunItFactoriesTest extends TestCase
{
    public function testNullRuntimeBuildsAndRuns(): void
    {
        $runtime = RunItFactories::nullRuntime();

        self::assertInstanceOf(RunItInterface::class, $runtime);
        self::assertSame('done', $runtime->run(
            ExecutionDefinition::create(ExecutionName::fromString('null.spec'), ExecutionKind::fromString('operation')),
            static fn (): string => 'done',
        ));
    }

    public function testInMemoryRuntimeBuildsAndRuns(): void
    {
        $runtime = RunItFactories::inMemory();

        self::assertInstanceOf(RunItInterface::class, $runtime);
        self::assertSame(42, $runtime->run(
            ExecutionDefinition::create(
                ExecutionName::fromString('memory.spec'),
                ExecutionKind::fromString('operation')
            ),
            static fn (): int => 42,
        ));
    }
}
