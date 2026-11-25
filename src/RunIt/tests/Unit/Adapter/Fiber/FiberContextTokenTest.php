<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter\Fiber;

use Codeception\Test\Unit;
use ItPack\RunIt\Adapter\Fiber\FiberContextToken;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

#[CoversClass(FiberContextToken::class)]
final class FiberContextTokenTest extends Unit
{
    public function testAnchorAndScopeAreExposed(): void
    {
        $anchor = new stdClass();
        $scope = $this->createMock(ExecutionScopeInterface::class);
        $token = new FiberContextToken($anchor, $scope);

        self::assertSame($anchor, $token->anchor);
        self::assertSame($scope, $token->scope);
        self::assertIsInt($token->id());
    }
}
