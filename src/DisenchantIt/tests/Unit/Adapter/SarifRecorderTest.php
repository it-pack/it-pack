<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Reporting\SarifRecorder;
use ItPack\DisenchantIt\ValueObject\Execution\ExecutionArtifactInterface;

final class SarifRecorderTest extends Unit
{
    private function artifact(array $normalized): ExecutionArtifactInterface
    {
        $artifact = $this->createMock(ExecutionArtifactInterface::class);
        $artifact->method('normalize')->willReturn($normalized);

        return $artifact;
    }

    public function testRecordReportsAndRender(): void
    {
        $recorder = new SarifRecorder();
        $recorder->record($this->artifact([
            'kind' => 'disenchant.it.magic_value.detected.v1',
            'payload' => ['x' => 1],
        ]));

        $this->assertCount(1, $recorder->reports());

        $sarif = $recorder->toSarif();
        $this->assertJson($sarif);
        $this->assertStringContainsString('"ruleId"', $sarif);
        $this->assertStringContainsString('"$schema"', $sarif);
    }

    public function testToSarifThrowsOnEncodeFailure(): void
    {
        $recorder = new SarifRecorder();
        $recorder->record($this->artifact(['kind' => tmpfile(), 'payload' => []]));

        $this->expectException(\RuntimeException::class);

        $recorder->toSarif();
    }
}
