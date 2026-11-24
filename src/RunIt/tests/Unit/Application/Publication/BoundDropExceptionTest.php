<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\BoundDropException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BoundDropException::class)]
final class BoundDropExceptionTest extends Unit
{
    public function testExceptionCanBeCreatedWithReason(): void
    {
        $exception = BoundDropException::forReason('drop-redacted-observation');

        self::assertSame(
            'Artifact dropped by data-protection bound "drop-redacted-observation".',
            $exception->getMessage()
        );
    }

    public function testExceptionCanBeCreatedWithDifferentReason(): void
    {
        $exception = BoundDropException::forReason('capacity-exceeded');

        self::assertSame(
            'Artifact dropped by data-protection bound "capacity-exceeded".',
            $exception->getMessage()
        );
    }
}
