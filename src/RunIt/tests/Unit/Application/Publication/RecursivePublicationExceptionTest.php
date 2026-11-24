<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\RecursivePublicationException;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(RecursivePublicationException::class)]
final class RecursivePublicationExceptionTest extends Unit
{
    public function testExceptionCanBeCreatedWithMessage(): void
    {
        $exception = new RecursivePublicationException('recursive publication detected');

        self::assertSame('recursive publication detected', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    public function testExceptionCanBeCreatedWithCodeAndPrevious(): void
    {
        $previous = new RuntimeException('previous');
        $exception = new RecursivePublicationException('recursive', 42, $previous);

        self::assertSame('recursive', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
