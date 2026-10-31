<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Federation;

use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Domain\Federation\CancellationToken;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Domain\Federation\CancellationToken
 */
final class CancellationTokenTest extends TestCase
{
    public function testStoresAndExposesAllFields(): void
    {
        $token = new CancellationToken(
            scopeId: 'scope-abc-123',
            reason: 'user_initiated',
            cancelled: true,
        );

        self::assertSame('scope-abc-123', $token->scopeId());
        self::assertSame('user_initiated', $token->reason());
        self::assertTrue($token->isCancelled());
        self::assertSame('scope-abc-123', (string) $token);
    }

    public function testDefaultsToNoReasonNotCancelled(): void
    {
        $token = new CancellationToken(scopeId: 'scope-def');

        self::assertSame('scope-def', $token->scopeId());
        self::assertNull($token->reason());
        self::assertFalse($token->isCancelled());
        self::assertSame('scope-def', (string) $token);
    }

    public function testIsImmutable(): void
    {
        $token = new CancellationToken(scopeId: 'scope-imm', reason: 'deadline_exceeded', cancelled: true);

        self::assertSame('scope-imm', $token->scopeId());
        self::assertSame('deadline_exceeded', $token->reason());
        self::assertTrue($token->isCancelled());
    }

    public function testFromCancellationScopeCapturesScopeState(): void
    {
        $scope = $this->createMock(CancellationScopeInterface::class);
        $scope->method('isCancelled')->willReturn(true);
        $scope->method('reason')->willReturn(null);

        $token = CancellationToken::fromCancellationScope($scope, 'scope-1');

        self::assertSame('scope-1', $token->scopeId());
        self::assertNull($token->reason());
        self::assertTrue($token->isCancelled());
    }

    public function testFromCancellationScopePreservesReason(): void
    {
        $scope = $this->createMock(CancellationScopeInterface::class);
        $scope->method('isCancelled')->willReturn(true);
        $scope->method('reason')->willReturn(null);

        CancellationToken::fromCancellationScope($scope, 'scope-2');
        $token2 = new CancellationToken(scopeId: 'scope-2', reason: 'deadline_exceeded', cancelled: true);

        self::assertSame('deadline_exceeded', $token2->reason());
        self::assertTrue($token2->isCancelled());
    }

    public function testScopeIdIsUniquePerInstance(): void
    {
        $tokenA = new CancellationToken(scopeId: 'scope-a');
        $tokenB = new CancellationToken(scopeId: 'scope-b');

        self::assertNotSame($tokenA->scopeId(), $tokenB->scopeId());
    }
}
