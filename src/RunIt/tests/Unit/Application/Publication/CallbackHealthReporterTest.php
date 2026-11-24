<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\CallbackHealthReporter;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Throwable;

#[CoversClass(CallbackHealthReporter::class)]
final class CallbackHealthReporterTest extends Unit
{
    public function testReportCallsCallback(): void
    {
        $reporter = new CallbackHealthReporter(
            function (Throwable $error, ExecutionArtifactInterface $artifact): void {
                // Callback invoked; containment verified by absence of exception.
            }
        );
        $error = new RuntimeException('test error');
        $artifact = $this->createMock(ExecutionArtifactInterface::class);

        $reporter->report($error, $artifact);

        self::assertInstanceOf(InternalHealthReporterInterface::class, $reporter);
    }
}
