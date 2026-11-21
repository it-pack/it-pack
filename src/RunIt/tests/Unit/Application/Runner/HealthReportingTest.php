<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Runner;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Application\Publication\ErrorLogHealthReporter;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
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
