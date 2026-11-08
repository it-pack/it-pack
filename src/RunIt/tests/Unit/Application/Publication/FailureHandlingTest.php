<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\CollectAndContinuePolicy;
use ItPack\RunIt\Application\Publication\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use ItPack\RunIt\Application\Publication\ReportToFallbackPolicy;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use RuntimeException;
use Throwable;

/**
 * @group application
 * @group publication
 */
final class FailureHandlingTest extends Unit
{
    private function artifact(): ExecutionArtifactInterface
    {
        return Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'hello'
        );
    }

    private function throwingSink(): CallbackArtifactPublisher
    {
        return new CallbackArtifactPublisher(static function (): never {
            throw new RuntimeException('transport down');
        });
    }

    public function testFailOpenSwallows(): void
    {
        $publisher = new FailSafeArtifactPublisher($this->throwingSink(), new FailOpenPolicy());

        $thrown = null;
        try {
            $publisher->publish($this->artifact());
        } catch (RuntimeException $runtimeException) {
            $thrown = $runtimeException;
        }

        self::assertNull($thrown);
    }

    public function testThrowingHealthCallbackReportsBothFailuresAndArtifactId(): void
    {
        $primary = new RuntimeException('transport down');
        $handler = new RuntimeException('callback down');
        $artifact = $this->artifact();
        $seen = [];
        $lines = [];
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $reported) use (&$seen, $handler): never {
                $seen[] = [$error, $reported];
                throw $handler;
            },
            new FallbackErrorLogReporter(static function (string $line) use (&$lines): void {
                $lines[] = $line;
            }),
        );
        $sink = new CallbackArtifactPublisher(static function () use ($primary): never {
            throw $primary;
        });

        new FailSafeArtifactPublisher($sink, new FailOpenPolicy(), $health)->publish($artifact);

        self::assertSame([[$primary, $artifact]], $seen);
        self::assertSame([
            '[runit] publication health fallback:  handler fault [RuntimeException] callback down '
                . 'while reporting [RuntimeException] transport down artifact=obs-1',
        ], $lines);
    }

    public function testThrowingFallbackLogSinkEscapesFailOpenPublisher(): void
    {
        $sinkFailure = new RuntimeException('log sink down');
        $health = new CallbackHealthReporter(
            static function (): never {
                throw new RuntimeException('callback down');
            },
            new FallbackErrorLogReporter(static function () use ($sinkFailure): never {
                throw $sinkFailure;
            }),
        );
        $publisher = new FailSafeArtifactPublisher($this->throwingSink(), new FailOpenPolicy(), $health);

        $caught = null;
        try {
            $publisher->publish($this->artifact());
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertSame($sinkFailure, $caught);
    }

    public function testArtifactNormalizationFailureEscapesBeforeFallbackLogging(): void
    {
        $normalizationFailure = new RuntimeException('normalization failed');
        $artifact = $this->createMock(ExecutionArtifactInterface::class);
        $artifact->expects($this->once())->method('normalize')->willThrowException($normalizationFailure);
        $lines = [];
        $health = new CallbackHealthReporter(
            static function (): never {
                throw new RuntimeException('callback down');
            },
            new FallbackErrorLogReporter(static function (string $line) use (&$lines): void {
                $lines[] = $line;
            }),
        );
        $publisher = new FailSafeArtifactPublisher($this->throwingSink(), new FailOpenPolicy(), $health);

        $caught = null;
        try {
            $publisher->publish($artifact);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertSame($normalizationFailure, $caught);
        self::assertSame([], $lines);
    }

    public function testFailClosedRethrowsOriginalAdapterError(): void
    {
        $publisher = new FailSafeArtifactPublisher($this->throwingSink(), new FailClosedPolicy());

        $thrown = null;
        try {
            $publisher->publish($this->artifact());
        } catch (RuntimeException $runtimeException) {
            $thrown = $runtimeException;
        }

        self::assertNotNull($thrown);
        self::assertSame('transport down', $thrown->getMessage());
    }

    public function testCollectAndContinueCapturesOriginalError(): void
    {
        $collected = [];
        $policy = new CollectAndContinuePolicy(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$collected): void {
                $collected[] = [$error, $artifact];
            }
        );

        $sink = $this->throwingSink();
        $artifact = $this->artifact();
        new FailSafeArtifactPublisher($sink, $policy)->publish($artifact);

        self::assertCount(1, $collected);
        self::assertSame('transport down', $collected[0][0]->getMessage());
        self::assertSame($artifact, $collected[0][1]);
    }

    public function testReportToFallbackForwardsArtifact(): void
    {
        $fallbackSeen = [];
        $fallback = new CallbackArtifactPublisher(
            static function (ExecutionArtifactInterface $artifact) use (&$fallbackSeen): void {
                $fallbackSeen[] = $artifact;
            }
        );

        $artifact = $this->artifact();
        $policy = new ReportToFallbackPolicy($fallback);
        new FailSafeArtifactPublisher($this->throwingSink(), $policy)->publish($artifact);

        self::assertSame([$artifact], $fallbackSeen);
    }

    public function testFallbackFailureCannotRecurse(): void
    {
        $healthReports = [];
        $failingFallback = new CallbackArtifactPublisher(static function (): never {
            throw new RuntimeException('fallback also down');
        });

        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$healthReports): void {
                $healthReports[] = $error;
            }
        );

        $policy = new ReportToFallbackPolicy($failingFallback, $health);

        $thrown = null;
        try {
            new FailSafeArtifactPublisher($this->throwingSink(), $policy)->publish($this->artifact());
        } catch (RuntimeException $runtimeException) {
            $thrown = $runtimeException;
        }

        self::assertNull($thrown, 'A failing fallback must not propagate and must not recurse.');
        self::assertCount(1, $healthReports);
        self::assertSame('fallback also down', $healthReports[0]->getMessage());
    }

    public function testFailSafeDoesNotAlterSinkSuccess(): void
    {
        $sink = new InMemoryArtifactPublisher();
        new FailSafeArtifactPublisher($sink, new FailOpenPolicy())->publish($this->artifact());

        self::assertCount(1, $sink->published());
    }
}
