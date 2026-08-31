<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\Rewrite\TargetKind;

final class TargetKindTest extends Unit
{
    public function testEveryCaseRoundTripsThroughBackedValue(): void
    {
        foreach (TargetKind::cases() as $case) {
            $rehydrated = TargetKind::from($case->value);
            $this->assertSame($case, $rehydrated);
        }
    }

    public function testCasesHaveStableBackedValues(): void
    {
        $this->assertSame('const', TargetKind::Const->value);
        $this->assertSame('enum', TargetKind::Enum->value);
        $this->assertSame('flag_set', TargetKind::FlagSet->value);
        $this->assertSame('shared_const', TargetKind::SharedConst->value);
    }
}
