<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\JsonReportRecorder;
use ItPack\DisenchantIt\ValueObject\Execution\ExecutionArtifactInterface;

final class JsonReportRecorderTest extends Unit
{
    public function testRecordAndRender(): void
    {
        $recorder = new JsonReportRecorder();
        $artifact = $this->createMock(ExecutionArtifactInterface::class);
        $artifact->method('normalize')->willReturn(['kind' => 'x', 'payload' => ['v' => 1]]);

        $recorder->record($artifact);

        $this->assertCount(1, $recorder->reports());
        $json = $recorder->toJson();
        $this->assertJson($json);
        $this->assertStringContainsString('"kind"', $json);
    }

    public function testToJsonThrowsOnEncodeFailure(): void
    {
        $recorder = new JsonReportRecorder();
        $artifact = $this->createMock(ExecutionArtifactInterface::class);
        $artifact->method('normalize')->willReturn(['payload' => tmpfile()]);

        $recorder->record($artifact);
        $this->expectException(\RuntimeException::class);

        $recorder->toJson();
    }
}
