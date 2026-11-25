<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Propagation\ContextToken;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ContextToken::class)]
final class ContextTokenTest extends Unit
{
    public function testIdAndScopeAreExposed(): void
    {
        $scope = $this->createMock(ExecutionScopeInterface::class);
        $token = new ContextToken(7, $scope);

        self::assertSame(7, $token->id);
        self::assertSame($scope, $token->scope);
        self::assertSame(7, $token->id());
    }
}
