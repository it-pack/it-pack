<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\DefaultSdkRedactor;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DefaultSdkRedactor::class)]
final class DefaultSdkRedactorTest extends Unit
{
    public function testRedactDelegatesToDelegate(): void
    {
        $redactor = new DefaultSdkRedactor();
        $artifact = $this->createMock(ExecutionArtifactInterface::class);

        self::assertInstanceOf(ExecutionArtifactInterface::class, $redactor->redact($artifact));
    }
}
