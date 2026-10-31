<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use InvalidArgumentException;
use ItPack\AsyncIt\Contract\Port\JitterStrategy;
use ItPack\AsyncIt\Contract\Port\RetryStrategy;
use ItPack\AsyncIt\Domain\RetryPolicy;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \ItPack\AsyncIt\Domain\RetryPolicy
 */
final class RetryPolicyTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(3, $policy->maxAttempts);
        self::assertSame(RetryStrategy::Exponential, $policy->strategy);
        self::assertSame(JitterStrategy::Full, $policy->jitter);
        self::assertSame(1_000_000_000, $policy->baseDelay->nanoseconds());
        self::assertSame(30_000_000_000, $policy->maxDelay->nanoseconds());
    }

    public function testIsRetryableReturnsTrueForMatchingClass(): void
    {
        $policy = new RetryPolicy(retryable: ['RuntimeException']);

        self::assertTrue($policy->isRetryable(RuntimeException::class));
    }

    public function testIsRetryableReturnsFalseForNonMatchingClass(): void
    {
        $policy = new RetryPolicy(retryable: ['RuntimeException']);

        self::assertFalse($policy->isRetryable(InvalidArgumentException::class));
    }

    public function testIsRetryableReturnsTrueForAllWhenEmpty(): void
    {
        $policy = new RetryPolicy(retryable: []);

        self::assertTrue($policy->isRetryable(RuntimeException::class));
    }

    public function testExponentialDelayDoublesPerAttempt(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(1_000_000_000),
            maxDelay: Duration::ofNanoseconds(60_000_000_000),
            strategy: RetryStrategy::Exponential,
            jitter: JitterStrategy::None,
        );

        self::assertSame(1_000_000_000, $policy->delayForAttempt(1)->nanoseconds());
        self::assertSame(2_000_000_000, $policy->delayForAttempt(2)->nanoseconds());
        self::assertSame(4_000_000_000, $policy->delayForAttempt(3)->nanoseconds());
    }

    public function testFixedDelayIsConstant(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(2_000_000_000),
            strategy: RetryStrategy::Fixed,
            jitter: JitterStrategy::None,
        );

        self::assertSame(2_000_000_000, $policy->delayForAttempt(1)->nanoseconds());
        self::assertSame(2_000_000_000, $policy->delayForAttempt(5)->nanoseconds());
    }

    public function testMaxDelayCapsExponentialBackoff(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(1_000_000_000),
            maxDelay: Duration::ofNanoseconds(4_000_000_000),
            strategy: RetryStrategy::Exponential,
            jitter: JitterStrategy::None,
        );

        self::assertSame(4_000_000_000, $policy->delayForAttempt(10)->nanoseconds());
    }

    public function testNoneJitterReturnsFullDelay(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(2_000_000_000),
            strategy: RetryStrategy::Fixed,
            jitter: JitterStrategy::None,
        );

        self::assertSame(2_000_000_000, $policy->delayForAttempt(1)->nanoseconds());
    }

    public function testFibonacciDelayFollowsSequence(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(1_000_000_000),
            strategy: RetryStrategy::Fibonacci,
            jitter: JitterStrategy::None,
        );

        self::assertSame(1_000_000_000, $policy->delayForAttempt(1)->nanoseconds());
        self::assertSame(1_000_000_000, $policy->delayForAttempt(2)->nanoseconds());
        self::assertSame(2_000_000_000, $policy->delayForAttempt(3)->nanoseconds());
        self::assertSame(3_000_000_000, $policy->delayForAttempt(4)->nanoseconds());
    }

    public function testFullJitterReturnsValueWithinRange(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(1_000_000_000),
            strategy: RetryStrategy::Exponential,
            jitter: JitterStrategy::Full,
        );

        for ($i = 0; $i < 10; $i++) {
            $delay = $policy->delayForAttempt(2)->nanoseconds();
            self::assertGreaterThanOrEqual(0, $delay);
            self::assertLessThanOrEqual(2_000_000_000, $delay);
        }
    }

    public function testFullJitterReturnsZeroForZeroDelay(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(0),
            strategy: RetryStrategy::Fixed,
            jitter: JitterStrategy::Full,
        );

        self::assertSame(0, $policy->delayForAttempt(1)->nanoseconds());
    }

    public function testEqualJitterReturnsValueWithinRange(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(1_000_000_000),
            strategy: RetryStrategy::Exponential,
            jitter: JitterStrategy::Equal,
        );

        for ($i = 0; $i < 10; $i++) {
            $delay = $policy->delayForAttempt(2)->nanoseconds();
            self::assertGreaterThanOrEqual(1_000_000_000, $delay);
            self::assertLessThanOrEqual(2_000_000_000, $delay);
        }
    }

    public function testEqualJitterReturnsZeroForZeroDelay(): void
    {
        $policy = new RetryPolicy(
            baseDelay: Duration::ofNanoseconds(0),
            strategy: RetryStrategy::Fixed,
            jitter: JitterStrategy::Equal,
        );

        self::assertSame(0, $policy->delayForAttempt(1)->nanoseconds());
    }
}
