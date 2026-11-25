<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Propagation;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Propagation\CompositeContextToken;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CompositeContextToken::class)]
final class CompositeContextTokenTest extends Unit
{
    public function testIdDelegatesToInnerToken(): void
    {
        $owner = $this->createMock(ContextStorageInterface::class);
        $inner = $this->createMock(ContextTokenInterface::class);
        $inner->method('id')->willReturn(42);
        $token = new CompositeContextToken($owner, $inner);

        self::assertSame($owner, $token->owner);
        self::assertSame($inner, $token->inner);
        self::assertSame(42, $token->id());
    }
}
