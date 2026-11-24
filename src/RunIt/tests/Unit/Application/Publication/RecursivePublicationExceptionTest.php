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
    public function testCanBeInstantiatedWithMessage(): void
    {
        $exception = new RecursivePublicationException('recursive publication');

        self::assertSame('recursive publication', $exception->getMessage());
    }

    public function testForNestedPublish(): void
    {
        $exception = RecursivePublicationException::forNestedPublish();

        self::assertStringContainsString('Publication re-entered', $exception->getMessage());
    }
}
