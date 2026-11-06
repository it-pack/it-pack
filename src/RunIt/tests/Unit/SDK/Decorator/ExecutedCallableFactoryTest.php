<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Decorator;

use ItPack\RunIt\Contract\Application\ExecutionRunnerInterface;
use ItPack\RunIt\SDK\Decorator\ExecutedCallable;
use ItPack\RunIt\SDK\Decorator\ExecutedCallableFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecutedCallableFactory::class)]
class ExecutedCallableFactoryTest extends TestCase
{
    public function testBuild(): void
    {
        $factory = new ExecutedCallableFactory(
            $this->createMock(ExecutionRunnerInterface::class),
        );

        $this->assertInstanceOf(
            ExecutedCallable::class,
            $factory->build(
                static fn() => true,
                null
            )
        );
    }
}
