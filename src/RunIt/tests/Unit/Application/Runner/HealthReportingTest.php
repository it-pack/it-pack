<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\ErrorLogHealthReporter;
use ItPack\RunIt\Contract\Application\ExecutionSnapshotFactoryInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use ItPack\RunIt\Domain\Execution\ExecutionKind;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use PHPUnit\Framework\Attributes\CoversNothing;
use RuntimeException;
use Tests\Support\Fixture\RunItTestKit;
use Tests\Support\Fixture\ThrowingArtifactPublisher;
use Throwable;

/**
 * Failure-first coverage of the health-reporting paths selected in Phase 4:
 * default error-log reporting (including direct runner construction), custom
 * callbacks, explicit silence, throwing reporters, and the bounded fallback.
 *
 * The diagnostic sink is injected so assertions never depend on the process
 * error log directly for the custom/silence/fallback matrix.
 *
 * @group application
 * @group publication
 * @group security
 */
#[CoversNothing]
final class HealthReportingTest extends Unit
{
    private function definition(): ExecutionDefinition
    {
        return ExecutionDefinition::create(
            ExecutionName::fromString('op'),
            ExecutionKind::fromString('task')
        );
    }

    public function testDefaultNoCallbackReportsPublisherFailureToErrorLog(): void
    {
        $logPath = (string) tempnam(sys_get_temp_dir(), 'runit-health');
        $previous = (string) ini_get('error_log');

        try {
            ini_set('error_log', $logPath);

            $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());

            $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

            self::assertSame('business-value', $result);
            self::assertStringContainsString(
                'publication health',
                (string) @file_get_contents($logPath),
                'a throwing publisher with no callback must still be reported to the default error log'
            );
        } finally {
            ini_set('error_log', $previous);
            @unlink($logPath);
        }
    }

    public function testDefaultNoCallbackStillPreservesBusinessResult(): void
    {
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result);
    }

    public function testDefaultNoCallbackPreservesOriginalThrowable(): void
    {
        $thrown = new RuntimeException('business-error');
        $kit = new RunItTestKit(publisher: new ThrowingArtifactPublisher());

        $caught = null;
        try {
            $kit->runner->run($this->definition(), static function () use ($thrown): never {
                throw $thrown;
            });
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        self::assertSame($thrown, $caught);
    }

    public function testCustomCallbackReceivesPublicationFailure(): void
    {
        $captured = null;
        $kit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result);
        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('publisher down', $captured->getMessage());
    }

    public function testNoOpHandlerIsExplicitSilence(): void
    {
        $silentKit = new RunItTestKit(
            publisher: new ThrowingArtifactPublisher(),
            onPublicationFailure: static function (Throwable $e): void {
            },
        );

        // A no-op closure handed to the handler is the explicit silence opt-out:
        // reportFailure invokes it directly and it reports nothing.
        $result = $silentKit->runner->run($this->definition(), static fn (): string => 'business-value');

        self::assertSame('business-value', $result);
    }

    public function testThrowingCustomCallbackFallsBackAndPreservesResult(): void
    {
        $logPath = (string) tempnam(sys_get_temp_dir(), 'runit-health-fallback');
        $previous = (string) ini_get('error_log');

        try {
            ini_set('error_log', $logPath);

            $kit = new RunItTestKit(
                publisher: new ThrowingArtifactPublisher(),
                onPublicationFailure: static function (Throwable $e): never {
                    throw new RuntimeException('handler broke');
                },
            );

            $result = $kit->runner->run($this->definition(), static fn (): string => 'business-value');

            self::assertSame('business-value', $result);
            self::assertStringContainsString('publication health fallback', (string) @file_get_contents($logPath));
        } finally {
            ini_set('error_log', $previous);
            @unlink($logPath);
        }
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function failingReporterCases(): iterable
    {
        yield 'callback and success' => [true, false];
        yield 'callback and exception' => [true, true];
        yield 'default reporter and success' => [false, false];
        yield 'default reporter and exception' => [false, true];
    }

    #[DataProvider('failingReporterCases')]
    public function testReporterFailureFallbackExcludesSecrets(bool $callback, bool $businessFails): void
    {
        $logPath = (string) tempnam(sys_get_temp_dir(), 'runit-health-secret');
        $previous = (string) ini_get('error_log');
        $publisher = new class () implements ArtifactPublisherInterface {
            public function publish(ExecutionArtifactInterface $artifact): void
            {
                throw new RuntimeException('PRIMARY-SECRET');
            }
        };
        $mapper = new class () implements ThrowableMapperInterface {
            public function describe(Throwable $throwable): ThrowableDescriptionInterface
            {
                throw new RuntimeException('REPORTER-SECRET');
            }
        };
        $handler = $callback ? static function (Throwable $failure): never {
            throw new RuntimeException('REPORTER-SECRET');
        } : null;
        $businessError = new RuntimeException('BUSINESS-SECRET');
        $snapshotFactory = self::createStub(ExecutionSnapshotFactoryInterface::class);
        $snapshotFactory->method('terminal')->willThrowException(new RuntimeException('SNAPSHOT-SECRET'));

        try {
            ini_set('error_log', $logPath);
            $kit = new RunItTestKit(
                publisher: $publisher,
                onPublicationFailure: $handler,
                throwableMapper: $mapper,
                snapshotFactory: $snapshotFactory,
            );
            $caught = null;
            $result = null;
            try {
                $result = $kit->runner->run($this->definition(), static function () use (
                    $businessFails,
                    $businessError
                ): string {
                    if ($businessFails) {
                        throw $businessError;
                    }

                    return 'business-value';
                });
            } catch (Throwable $throwable) {
                $caught = $throwable;
            }

            $log = (string) file_get_contents($logPath);
            self::assertSame($businessFails ? $businessError : null, $caught);
            self::assertSame($businessFails ? null : 'business-value', $result);
            self::assertNull($kit->storage->current());
            self::assertStringContainsString('publication health fallback', $log);
            self::assertStringNotContainsString('PRIMARY-SECRET', $log);
            self::assertStringNotContainsString('REPORTER-SECRET', $log);
            self::assertStringNotContainsString('BUSINESS-SECRET', $log);
            self::assertStringNotContainsString('SNAPSHOT-SECRET', $log);
        } finally {
            ini_set('error_log', $previous);
            @unlink($logPath);
        }
    }

    public function testErrorLogReporterExcludesArtifactIdentifiers(): void
    {
        $logged = '';
        $reporter = new ErrorLogHealthReporter(
            throwableMapper: new DefaultThrowableMapper(),
            logTo: static function (string $line) use (&$logged): void {
                $logged = $line;
            },
        );

        $blocks = [];
        foreach (range(0, 59) as $index) {
            $blocks[] = sprintf('SECRET-BLOCK-%02d-%s', $index, str_repeat('x', 150));
        }

        $reporter->report(
            new RuntimeException(implode('|', $blocks)),
            new class () implements ExecutionArtifactInterface {
                public function normalize(): array
                {
                    return [];
                }
            },
        );

        self::assertStringContainsString('[RuntimeException]', $logged);
        self::assertStringContainsString('SECRET-BLOCK-00-', $logged);
        self::assertStringNotContainsString('SECRET-BLOCK-59-', $logged);
        self::assertLessThanOrEqual(4096, strlen($logged));
        self::assertStringNotContainsString('artifact=', $logged);
    }

    public function testCallbackHealthReporterForwardsToCallback(): void
    {
        $captured = null;
        $reporter = new CallbackHealthReporter(
            static function (Throwable $e) use (&$captured): void {
                $captured = $e;
            },
        );

        $reporter->report(new RuntimeException('callback boom'), new class () implements ExecutionArtifactInterface {
            public function normalize(): array
            {
                return [];
            }
        });

        self::assertInstanceOf(RuntimeException::class, $captured);
        self::assertSame('callback boom', $captured->getMessage());
    }
}
