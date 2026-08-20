<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\LiteralSubkind;

final class LiteralSubkindTest extends Unit
{
    public function testEveryCaseRoundTripsThroughBackedValue(): void
    {
        foreach (LiteralSubkind::cases() as $case) {
            $this->assertSame($case, LiteralSubkind::from($case->value));
        }
    }

    public function testKnownSubkindsHaveStableValues(): void
    {
        $this->assertSame('unknown', LiteralSubkind::Unknown->value);
        $this->assertSame('url', LiteralSubkind::Url->value);
        $this->assertSame('regex', LiteralSubkind::Regex->value);
        $this->assertSame('json', LiteralSubkind::Json->value);
        $this->assertSame('sql', LiteralSubkind::Sql->value);
        $this->assertSame('version', LiteralSubkind::Version->value);
        $this->assertSame('status_code', LiteralSubkind::StatusCode->value);
    }
}
