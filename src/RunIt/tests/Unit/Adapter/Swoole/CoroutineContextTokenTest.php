<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Swoole;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Swoole\CoroutineContextToken;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CoroutineContextToken::class)]
final class CoroutineContextTokenTest extends Unit
{
    public function testCoroutineIdAndScopeAreExposed(): void
    {
        $scope = $this->createMock(ExecutionScopeInterface::class);
        $token = new CoroutineContextToken(3, $scope);

        self::assertSame(3, $token->coroutineId);
        self::assertSame($scope, $token->scope);
        self::assertSame(3, $token->id());
    }
}
