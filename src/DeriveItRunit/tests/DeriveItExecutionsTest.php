<?php

declare(strict_types=1);

namespace ItPack\DeriveItRunit\Tests;

use ItPack\DeriveItRunit\DeriveItExecutions;
use ItPack\RunIt\Domain\Execution\ExecutionDefinition;
use PHPUnit\Framework\TestCase;

final class DeriveItExecutionsTest extends TestCase
{
    public function testDefinitionCarriesNameAndCanonicalKind(): void
    {
        $definition = DeriveItExecutions::definition('derive.family');

        self::assertInstanceOf(ExecutionDefinition::class, $definition);
        self::assertSame('derive.family', $definition->name()->toString());
        self::assertSame(DeriveItExecutions::EXECUTION_KIND, $definition->kind()->toString());
        self::assertSame('derivation.run', $definition->kind()->toString());
    }
}
