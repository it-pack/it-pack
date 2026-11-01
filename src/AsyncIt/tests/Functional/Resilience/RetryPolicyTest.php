<?php

declare(strict_types=1);

namespace Tests\Functional\Resilience;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\Port\JitterStrategy;
use ItPack\AsyncIt\Contract\Port\RetryStrategy;
use ItPack\AsyncIt\Domain\RetryPolicy;
use ItPack\RunIt\Adapter\Common\SystemMonotonicClock;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Functional\FunctionalTestHelper;

/**
 * @covers \ItPack\AsyncIt\Domain\RetryPolicy
 * @covers \ItPack\AsyncIt\Application\Scheduler\FiberTaskScheduler
 */
final class RetryPolicyTest extends TestCase
{
    public function testRetryReturnsResultOnFirstSuccess(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $policy = new RetryPolicy(
            maxAttempts: 3,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
        );
        $calls = 0;

        $result = $scheduler->retry(static function () use (&$calls): string {
            $calls++;

            return 'success';
        }, $policy);

        self::assertSame('success', $result);
        self::assertSame(1, $calls);
    }

    public function testRetryRetriesOnFailureThenSucceeds(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler(new SystemMonotonicClock());
        $policy = new RetryPolicy(
            maxAttempts: 3,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
        );
        $calls = 0;
        $result = null;

        $scheduler->spawn(static function () use ($scheduler, $policy, &$calls, &$result): void {
            $result = $scheduler->retry(static function () use (&$calls): string {
                $calls++;

                if ($calls < 3) {
                    throw new RuntimeException('transient');
                }

                return 'recovered';
            }, $policy);
        });

        $scheduler->run();

        self::assertSame('recovered', $result);
        self::assertSame(3, $calls);
    }

    public function testRetryExhaustsMaxAttemptsAndRethrowsOriginal(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $policy = new RetryPolicy(
            maxAttempts: 1,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
        );
        $calls = 0;
        $scheduler->spawn(static function () use ($scheduler, $policy, &$calls): void {
            $scheduler->retry(static function () use (&$calls): never {
                $calls++;
                throw new RuntimeException('always fails');
            }, $policy);
        });

        try {
            $scheduler->run();
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('always fails', $runtimeException->getMessage());
            self::assertSame(1, $calls);
        }
    }

    public function testRetryDoesNotRetryNonRetryableException(): void
    {
        $scheduler = FunctionalTestHelper::makeScheduler();
        $policy = new RetryPolicy(
            maxAttempts: 3,
            baseDelay: Duration::ofNanoseconds(1),
            jitter: JitterStrategy::None,
            retryable: ['RuntimeException'],
        );

        $scheduler->spawn(static function () use ($scheduler, $policy): void {
            $scheduler->retry(static fn (): never => throw new InvalidArgumentException('non-retryable'), $policy);
        });

        try {
            $scheduler->run();
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('non-retryable', $invalidArgumentException->getMessage());
        }
    }

    public function testRetryPolicyDelayForAttemptGrowsExponentially(): void
    {
        $policy = new RetryPolicy(
            maxAttempts: 5,
            baseDelay: Duration::ofNanoseconds(1_000_000),
            maxDelay: Duration::ofNanoseconds(1_000_000_000),
            strategy: RetryStrategy::Exponential,
            jitter: JitterStrategy::None,
        );

        $d1 = $policy->delayForAttempt(1)->nanoseconds();
        $d2 = $policy->delayForAttempt(2)->nanoseconds();
        $d3 = $policy->delayForAttempt(3)->nanoseconds();

        self::assertSame(1_000_000, $d1);
        self::assertSame(2_000_000, $d2);
        self::assertSame(4_000_000, $d3);
    }
}
