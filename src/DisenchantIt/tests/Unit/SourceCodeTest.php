<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\SourceCode;

final class SourceCodeTest extends Unit
{
    public function testIdentityIsStableAcrossEqualInstances(): void
    {
        $a = new SourceCode('<?php echo 1;');
        $b = new SourceCode('<?php echo 1;');
        $c = new SourceCode('<?php echo 2;');

        // Same content → identical identity even for distinct objects.
        $this->assertSame($a->identity(), $b->identity());
        // Different content → different identity.
        $this->assertNotSame($a->identity(), $c->identity());
    }

    public function testCodeIsHeldVerbatim(): void
    {
        $code = '<?php class C {}';
        $this->assertSame($code, new SourceCode($code)->code);
    }
}
