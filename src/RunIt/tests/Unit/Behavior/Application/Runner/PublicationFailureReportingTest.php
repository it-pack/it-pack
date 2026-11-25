<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Application\Runner;

use Closure;
use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Application\Runner\PublicationFailureReporting;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Domain\Shared\ThrowableDescription;
use PHPUnit\Framework\Attributes\CoversTrait;
use RuntimeException;
use Tests\Support\Fixture\FailureReportingFixtureInterface;
use Throwable;

/**
 * Verifies the shared publication-failure reporting guard through an anonymous
 * class that consumes the trait: callback invocation, callback fallback, error-log
 * fallback, and recursion prevention never replace the business outcome.
 *
 * @group behavior
 * @group runner
 */
#[CoversTrait(PublicationFailureReporting::class)]
final class PublicationFailureReportingTest extends Unit
{
    public function testCallbackIsInvokedAndNotFallback(): void
    {
        $called = false;
        $fixture = $this->fixture(
            static function (Throwable $failure) use (&$called): void {
                $called = true;
            },
        );

        $fixture->report(new RuntimeException('boom'));

        self::assertTrue($called);
    }

    public function testThrowingCallbackFallsBackWithoutRethrowing(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'runit_error_log_');
        $previousErrorLog = ini_set('error_log', $tmpFile);
        $logged = '';

        try {
            $calls = 0;
            $fixture = $this->fixture(
                static function (Throwable $failure) use (&$calls): never {
                    ++$calls;
                    throw new RuntimeException('callback broken');
                },
            );

            $fixture->report(new RuntimeException('boom'));

            $logged = file_get_contents($tmpFile);
        } finally {
            ini_set('error_log', $previousErrorLog);
            if ($tmpFile !== false && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        self::assertSame(1, $calls);
        self::assertIsString($logged);
        self::assertStringContainsString('[runit] publication health fallback:', $logged);
    }

    public function testNoCallbackReportsToErrorLogWithoutRethrowing(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'runit_error_log_');
        $previousErrorLog = ini_set('error_log', $tmpFile);
        $logged = '';

        try {
            $fixture = $this->fixture(null);

            $fixture->report(new RuntimeException('boom'));

            $logged = file_get_contents($tmpFile);
        } finally {
            ini_set('error_log', $previousErrorLog);
            if ($tmpFile !== false && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        self::assertIsString($logged);
        self::assertStringContainsString('[runit] publication health:', $logged);
    }

    public function testRecursiveReportingRoutesToFallback(): void
    {
        /** @var FailureReportingFixtureInterface|null $fixture */
        $fixture = null;
        $calls = 0;
        $fixture = $this->fixture(
            static function (Throwable $failure) use (&$fixture, &$calls): void {
                // Nested report while already reporting must not recurse.
                ++$calls;
                $fixture?->report(new RuntimeException('nested'));
            },
        );

        $fixture->report(new RuntimeException('boom'));

        self::assertSame(1, $calls);

        // Recursion guard must reset so a second independent report invokes the callback again.
        $fixture->report(new RuntimeException('boom again'));

        self::assertSame(2, $calls);
    }

    private function fixture(?Closure $onPublicationFailure): FailureReportingFixtureInterface
    {
        return new class (
            $onPublicationFailure,
            new class () implements ThrowableMapperInterface {
                public function describe(Throwable $throwable): ThrowableDescription
                {
                    return ThrowableDescription::create(
                        $throwable::class,
                        $throwable->getMessage(),
                        $throwable->getCode(),
                        previous: null,
                        trace: null,
                    );
                }
            }
        ) implements FailureReportingFixtureInterface {
            use PublicationFailureReporting;

            public function __construct(
                private readonly ?Closure $onPublicationFailure,
                private readonly ThrowableMapperInterface $throwableMapper,
            ) {
            }

            public function report(Throwable $failure, ?ExecutionArtifactInterface $artifact = null): void
            {
                $this->reportFailure($failure, $artifact);
            }
        };
    }
}
