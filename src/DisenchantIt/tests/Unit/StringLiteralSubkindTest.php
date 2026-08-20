<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\LiteralSubkind;
use ItPack\DisenchantIt\ValueObject\StringLiteral;

final class StringLiteralSubkindTest extends Unit
{
    public function testSubkindDefaultsToUnknownWhenOmitted(): void
    {
        $lit = new StringLiteral('admin-user', 1, LiteralRole::Unclassified, 'C', 'm');

        $this->assertSame(LiteralSubkind::Unknown, $lit->subkind);
    }

    public function testSubkindIsExposedWhenProvided(): void
    {
        $lit = new StringLiteral(
            value: 'https://example.com',
            line: 1,
            role: LiteralRole::Unclassified,
            className: 'C',
            methodName: 'm',
            subkind: LiteralSubkind::Url,
        );

        $this->assertSame(LiteralSubkind::Url, $lit->subkind);
    }
}
