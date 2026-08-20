<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use Codeception\Test\Unit;
use ItPack\CommandIt\Contract\Exception\HandlerNotFoundException;

/**
 * @covers \ItPack\CommandIt\Contract\Exception\HandlerNotFoundException
 */
final class HandlerNotFoundExceptionTest extends Unit
{
    public function testForCommandCarriesTheName(): void
    {
        $error = HandlerNotFoundException::forCommand('demo:run');

        self::assertInstanceOf(HandlerNotFoundException::class, $error);
        self::assertSame('No handler is registered for command "demo:run".', $error->getMessage());
    }
}
