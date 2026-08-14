<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Context\ContextSource;

final class ContextSourceTest extends Unit
{
    public function testFromStringProjectsAndRenders(): void
    {
        $source = ContextSource::fromString('Authorization');

        self::assertSame('Authorization', $source->toString());
        self::assertSame('Authorization', (string) $source);
    }

    public function testEqualsIsValueBased(): void
    {
        self::assertTrue(ContextSource::fromString('x')->equals(ContextSource::fromString('x')));
        self::assertFalse(ContextSource::fromString('x')->equals(ContextSource::fromString('y')));
    }
}
