<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Memory\InMemoryArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackArtifactPublisher;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\FailSafeArtifactPublisher;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use ItPack\RunIt\Application\Publication\Policy\CollectAndContinuePolicy;
use ItPack\RunIt\Application\Publication\Policy\FailClosedPolicy;
use ItPack\RunIt\Application\Publication\Policy\FailOpenPolicy;
use ItPack\RunIt\Application\Publication\Policy\ReportToFallbackPolicy;
use ItPack\RunIt\Application\Publication\RecursivePublicationException;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Throwable;

/**
 * @group application
 * @group publication
 */
#[CoversClass(CallbackHealthReporter::class)]
#[CoversClass(CollectAndContinuePolicy::class)]
#[CoversClass(FailClosedPolicy::class)]
#[CoversClass(FailOpenPolicy::class)]
#[CoversClass(FailSafeArtifactPublisher::class)]
#[CoversClass(ReportToFallbackPolicy::class)]
#[UsesClass(InMemoryArtifactPublisher::class)]
#[UsesClass(CallbackArtifactPublisher::class)]
#[UsesClass(FallbackErrorLogReporter::class)]
#[UsesClass(RecursivePublicationException::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(Observation::class)]
#[UsesClass(ObservationId::class)]
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

    public function testRecursiveHealthPublicationReentersTheReporter(): void
    {
        $errors = [];
        $calls = 0;
        $publisher = null;
        $health = new CallbackHealthReporter(
            static function (Throwable $error, ExecutionArtifactInterface $artifact) use (&$errors, &$publisher): void {
                $errors[] = $error;
                if (count($errors) < 4) {
                    self::assertInstanceOf(FailSafeArtifactPublisher::class, $publisher);
                    $publisher->publish($artifact);
                }
            },
        );
        $sink = new CallbackArtifactPublisher(static function () use (&$calls): never {
            ++$calls;
            throw new RuntimeException('transport down');
        });
        $publisher = new FailSafeArtifactPublisher($sink, new FailOpenPolicy(), $health);

        $publisher->publish($this->artifact());

        self::assertSame(1, $calls);
        self::assertCount(4, $errors);
        self::assertSame('transport down', $errors[0]->getMessage());
        foreach (array_slice($errors, 1) as $error) {
            self::assertInstanceOf(RecursivePublicationException::class, $error);
        }

        $publisher->publish($this->artifact());
        self::assertSame(2, $calls);
        self::assertCount(5, $errors);
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

    public function testThrowingFallbackLogSinkDoesNotPreventFailurePolicy(): void
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
        $handled = [];
        $policy = new CollectAndContinuePolicy(static function (Throwable $error) use (&$handled): void {
            $handled[] = $error->getMessage();
        });
        $publisher = new FailSafeArtifactPublisher($this->throwingSink(), $policy, $health);

        $caught = null;
        try {
            $publisher->publish($this->artifact());
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertNull($caught);
        self::assertSame(['transport down'], $handled);
    }

    public function testSystemFallbackLogFailureIsNotReportedToPublisher(): void
    {
        if (!is_writable('/dev/full') || @file_put_contents('/dev/full', 'probe') !== false) {
            self::markTestSkipped('A writable /dev/full device that rejects writes is required.');
        }

        $previousLog = ini_set('error_log', '/dev/full');
        if ($previousLog === false) {
            self::markTestSkipped('The PHP error_log setting cannot be changed.');
        }

        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });

        try {
            $health = new CallbackHealthReporter(static function (): never {
                throw new RuntimeException('callback down');
            });
            $handled = [];
            $policy = new CollectAndContinuePolicy(
                static function (Throwable $error) use (&$handled): void {
                    $handled[] = $error->getMessage();
                },
            );

            new FailSafeArtifactPublisher($this->throwingSink(), $policy, $health)->publish($this->artifact());

            self::assertSame(['transport down'], $handled);
            self::assertSame([], $warnings);
        } finally {
            restore_error_handler();
            ini_set('error_log', $previousLog);
        }
    }

    public function testArtifactNormalizationFailureDoesNotPreventFailurePolicy(): void
    {
        $normalizationFailure = new RuntimeException('normalization failed');
        $artifact = $this->createMock(ExecutionArtifactInterface::class);
        $artifact->expects($this->once())->method('normalize')->willThrowException($normalizationFailure);
        $health = new CallbackHealthReporter(
            static function (): never {
                throw new RuntimeException('callback down');
            },
            new FallbackErrorLogReporter(static function (): void {
            }),
        );
        $handled = [];
        $policy = new CollectAndContinuePolicy(static function (Throwable $error) use (&$handled): void {
            $handled[] = $error->getMessage();
        });
        $publisher = new FailSafeArtifactPublisher($this->throwingSink(), $policy, $health);

        $caught = null;
        try {
            $publisher->publish($artifact);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertNull($caught);
        self::assertSame(['transport down'], $handled);
    }

    public function testSecondaryFailureReachesSanitizedSystemFallback(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'runit-health-');
        self::assertNotFalse($path);
        $oldLog = ini_get('error_log');
        $oldEnabled = ini_get('log_errors');
        self::assertNotFalse($oldLog);
        self::assertNotFalse($oldEnabled);

        try {
            ini_set('error_log', $path);
            ini_set('log_errors', '1');
            foreach (['normalization', 'fallback'] as $source) {
                $artifact = $this->createMock(ExecutionArtifactInterface::class);
                if ($source === 'normalization') {
                    $artifact->method('normalize')->willThrowException(new RuntimeException('secret normalization'));
                } else {
                    $artifact->method('normalize')->willReturn(['id' => 'secret artifact']);
                }

                $reporter = new CallbackHealthReporter(
                    static function (): never {
                        throw new RuntimeException('secret callback');
                    },
                    new FallbackErrorLogReporter(static function (): never {
                        throw new RuntimeException('secret sink');
                    }),
                );
                $reporter->report(new RuntimeException('secret primary'), $artifact);
            }

            $log = file_get_contents($path);
            self::assertIsString($log);
            self::assertSame(2, substr_count($log, 'publication health fallback'));
            self::assertSame(2, substr_count($log, 'Fallback failure: RuntimeException'));
            self::assertStringNotContainsString('secret', $log);
        } finally {
            ini_set('error_log', $oldLog);
            ini_set('log_errors', $oldEnabled);
            unlink($path);
        }
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

    public function testFailClosedPreservesOriginalErrorWhenHealthFallbackFails(): void
    {
        foreach (['normalization', 'fallback'] as $source) {
            $original = new RuntimeException('original publication failure');
            $artifact = $this->createMock(ExecutionArtifactInterface::class);
            if ($source === 'normalization') {
                $artifact->method('normalize')->willThrowException(new RuntimeException('normalization failure'));
            } else {
                $artifact->method('normalize')->willReturn([]);
            }

            $health = new CallbackHealthReporter(
                static function (): never {
                    throw new RuntimeException('callback failure');
                },
                new FallbackErrorLogReporter(static function (): never {
                    throw new RuntimeException('fallback failure');
                }),
            );
            $publisher = new FailSafeArtifactPublisher(
                new CallbackArtifactPublisher(static function () use ($original): never {
                    throw $original;
                }),
                new FailClosedPolicy(),
                $health,
            );
            $caught = null;
            try {
                $publisher->publish($artifact);
            } catch (Throwable $error) {
                $caught = $error;
            }

            self::assertSame($original, $caught);
        }
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
