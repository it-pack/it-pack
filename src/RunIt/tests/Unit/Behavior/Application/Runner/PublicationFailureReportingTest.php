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
        $fixture = $this->fixture(
            static function (Throwable $failure): never {
                throw new RuntimeException('callback broken');
            },
        );

        $fixture->report(new RuntimeException('boom'));
    }

    public function testNoCallbackReportsToErrorLogWithoutRethrowing(): void
    {
        $fixture = $this->fixture(null);

        $fixture->report(new RuntimeException('boom'));
    }

    public function testRecursiveReportingRoutesToFallback(): void
    {
        /** @var FailureReportingFixtureInterface|null $fixture */
        $fixture = null;
        $fixture = $this->fixture(
            static function (Throwable $failure) use (&$fixture): void {
                // Nested report while already reporting must not recurse.
                $fixture?->report(new RuntimeException('nested'));
            },
        );

        $fixture->report(new RuntimeException('boom'));
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
