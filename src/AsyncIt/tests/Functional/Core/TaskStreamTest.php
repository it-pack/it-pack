<?php

declare(strict_types=1);

namespace Tests\Functional\Core;

use Generator;
use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Application\Factory\TaskStreamFactory;
use ItPack\AsyncIt\Contract\TaskStreamInterface;
use PHPUnit\Framework\TestCase;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Domain\TaskStream
 * @covers \ItPack\AsyncIt\Application\Factory\TaskStreamFactory
 * @covers \ItPack\AsyncIt\SDK\AsyncRuntime
 */
final class TaskStreamTest extends TestCase
{
    public function testStreamYieldsResultsOneAtATime(): void
    {
        $fixture = FunctionalTestHelper::makeRuntimeWithClock(new DeterministicMonotonicClock());
        $runtime = $fixture['runtime'];

        $results = $runtime->run($fixture['rootScope'], static function () use ($runtime): array {
            $collected = [];

            $stream = $runtime->stream(static function (): Generator {
                yield static fn (): int => 1;
                yield static fn (): int => 2;
                yield static fn (): int => 3;
            });

            $stream->rewind();

            while ($stream->valid()) {
                $collected[] = $stream->current();
                $stream->next();
            }

            return $collected;
        });

        self::assertSame([1, 2, 3], $results);
    }

    public function testStreamEmptyGeneratorReturnsNothing(): void
    {
        $fixture = FunctionalTestHelper::makeRuntimeWithClock(new DeterministicMonotonicClock());
        $runtime = $fixture['runtime'];

        $results = $runtime->run($fixture['rootScope'], static function () use ($runtime): array {
            $collected = [];

            $stream = $runtime->stream(static function (): Generator {
                yield from [];
            });

            $stream->rewind();

            while ($stream->valid()) {
                $collected[] = $stream->current();
                $stream->next();
            }

            return $collected;
        });

        self::assertSame([], $results);
    }

    public function testStreamBackpressureRunsSequentially(): void
    {
        $fixture = FunctionalTestHelper::makeRuntimeWithClock(new DeterministicMonotonicClock());
        $runtime = $fixture['runtime'];

        $order = $runtime->run($fixture['rootScope'], static function () use ($runtime): array {
            $collected = [];

            $stream = $runtime->stream(function () use (&$collected): Generator {
                yield static function () use (&$collected): void {
                    $collected[] = 'first';
                };
                yield static function () use (&$collected): void {
                    $collected[] = 'second';
                };
            });

            $stream->rewind();

            while ($stream->valid()) {
                $stream->current();
                $stream->next();
            }

            return $collected;
        });

        self::assertSame(['first', 'second'], $order);
    }

    public function testStreamFactoryCreatesStream(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $generator = (function (): Generator {
            yield static fn (): string => 'hello';
        })();

        $factory = new TaskStreamFactory();
        $stream = $factory->create($scheduler, $generator);

        self::assertInstanceOf(TaskStreamInterface::class, $stream);
    }
}
