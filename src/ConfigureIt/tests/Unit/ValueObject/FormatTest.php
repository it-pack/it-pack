<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\ConfigureIt\ValueObject\Format;

class FormatTest extends Unit
{
    public function testEnumCasesAreStable(): void
    {
        verify(Format::PHP->value)->equals('php');
        verify(Format::JSON->value)->equals('json');
        verify(Format::ENV->value)->equals('env');
    }

    public function testFromStringParsesKnownFormats(): void
    {
        verify(Format::fromString('JSON')->value)->equals('json');
        verify(Format::fromString(' yaml ')->value)->equals('yaml');
    }

    public function testFromStringRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Format::fromString('xmlnope');
    }
}
