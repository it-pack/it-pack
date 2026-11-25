<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\PublicationGuardToken;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PublicationGuardToken::class)]
final class PublicationGuardTokenTest extends Unit
{
    public function testIdIsReturned(): void
    {
        $token = new PublicationGuardToken('guard-1');

        self::assertSame('guard-1', $token->id());
    }
}
