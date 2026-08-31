<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfig;

final class SemConvConfigTest extends Unit
{
    public function testDefaultOutputPathAndFullyQualifiedName(): void
    {
        $c = new SemConvConfig();

        $this->assertSame('SemConv.php', $c->outputPath);
        $this->assertSame('Generated\\SemConv', $c->fullyQualifiedName());
    }

    public function testOutputPathOverride(): void
    {
        $c = new SemConvConfig(outputPath: 'custom/Registry.php');

        $this->assertSame('custom/Registry.php', $c->outputPath);
    }

    public function testFullyQualifiedNameWithoutNamespace(): void
    {
        $c = new SemConvConfig(namespace: null);

        $this->assertSame('SemConv', $c->fullyQualifiedName());
    }

    public function testCustomNamespaceAndInterface(): void
    {
        $c = new SemConvConfig(interfaceName: 'Reg', namespace: 'App');

        $this->assertSame('App\\Reg', $c->fullyQualifiedName());
        $this->assertSame('Reg.php', $c->outputPath);
    }
}
