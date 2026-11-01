<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Application\Federation\TraceContextInjector;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;
use PHPUnit\Framework\TestCase;
use WeakMap;

/**
 * @covers \ItPack\AsyncIt\Application\Federation\TraceContextInjector
 */
final class TraceContextInjectorTest extends TestCase
{
    public function testInjectsTraceIdPerTask(): void
    {
        $idGenerator = new class () implements IdentifierGeneratorInterface {
            public function generate(): string
            {
                return 'trace-123';
            }
        };

        /** @var WeakMap<object, string> */
        $traceIds = new WeakMap();
        $injector = new TraceContextInjector($traceIds, $idGenerator);
        $task = new TraceContextFakeTask('t1');

        $result = $injector->process($task, fn (): string => 'done');

        self::assertSame('done', $result);
    }

    public function testGenerateIsCalledPerTask(): void
    {
        $callLog = new CallLog();
        $idGenerator = new readonly class ($callLog) implements IdentifierGeneratorInterface {
            public function __construct(
                private CallLog $callLog,
            ) {
            }

            public function generate(): string
            {
                $this->callLog->entries[] = 'generate';

                return 'trace-' . \count($this->callLog->entries);
            }
        };

        /** @var WeakMap<object, string> */
        $traceIds = new WeakMap();
        $injector = new TraceContextInjector($traceIds, $idGenerator);
        $task1 = new TraceContextFakeTask('t1');
        $task2 = new TraceContextFakeTask('t2');

        $injector->process($task1, fn (): string => 'r1');
        $injector->process($task2, fn (): string => 'r2');

        self::assertSame(['generate', 'generate'], $callLog->entries);
    }
}
