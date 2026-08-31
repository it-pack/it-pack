<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;

final class UseInserterHarness
{
    use \ItPack\DisenchantIt\Adapter\Rewriting\NamespaceUseInserterTrait;

    public function run(string $code, string $fqcn): string
    {
        return $this->insertUseStatement($code, $fqcn);
    }
}

final class NamespaceUseInserterTraitTest extends Unit
{
    private function harness(): UseInserterHarness
    {
        return new UseInserterHarness();
    }

    public function testReturnsUnchangedWhenNoAnchor(): void
    {
        $code = "<?php\nclass C {}\n";

        $this->assertSame($code, $this->harness()->run($code, 'App\\Foo'));
    }

    public function testInsertsAfterLastUse(): void
    {
        $code = "<?php\nnamespace App;\n\nuse App\\Bar;\n\nclass C {}\n";
        $result = $this->harness()->run($code, 'App\\Foo');

        $this->assertStringContainsString('use App\\Foo;', $result);
        $this->assertGreaterThan(
            strpos($result, 'use App\\Bar;'),
            strpos($result, 'use App\\Foo;'),
        );
    }

    public function testAlreadyImportedIsIdempotent(): void
    {
        $code = "<?php\nnamespace App;\n\nuse App\\Foo;\n\nclass C {}\n";

        $this->assertSame($code, $this->harness()->run($code, 'App\\Foo'));
    }

    public function testInsertsAfterNamespaceWhenNoUse(): void
    {
        $code = "<?php\nnamespace App;\n\nclass C {}\n";
        $result = $this->harness()->run($code, 'App\\Foo');

        $this->assertStringContainsString('use App\\Foo;', $result);
        $this->assertGreaterThan(
            strpos($result, 'namespace App;'),
            strpos($result, 'use App\\Foo;'),
        );
    }
}
