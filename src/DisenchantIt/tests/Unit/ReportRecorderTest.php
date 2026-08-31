<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Reporting\JsonReportRecorder;
use ItPack\DisenchantIt\Adapter\Reporting\SarifRecorder;
use ItPack\DisenchantIt\ValueObject\Execution\DisenchantOccurrence;

final class ReportRecorderTest extends Unit
{
    public function testJsonRecorderAccumulatesAndRenders(): void
    {
        $recorder = new JsonReportRecorder();
        $recorder->record(new DisenchantOccurrence('disenchant.it.magic_value.detected.v1', ['count' => 3]));
        $recorder->record(new DisenchantOccurrence('disenchant.it.rewrite.v1', ['count' => 2]));

        $json = $recorder->toJson();
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(2, $decoded);
        $this->assertSame('disenchant.it.magic_value.detected.v1', $decoded[0]['kind']);
        $this->assertSame(3, $decoded[0]['payload']['count']);
        $this->assertSame('disenchant.it.rewrite.v1', $decoded[1]['kind']);
    }

    public function testSarifRecorderProducesValidSarifLog(): void
    {
        $recorder = new SarifRecorder();
        $recorder->record(new DisenchantOccurrence('disenchant.it.magic_value.detected.v1', ['count' => 3]));

        $sarif = $recorder->toSarif();
        $decoded = json_decode($sarif, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('2.1.0', $decoded['version']);
        $this->assertArrayHasKey('runs', $decoded);
        $this->assertCount(1, $decoded['runs']);
        $this->assertSame('DisenchantIt', $decoded['runs'][0]['tool']['driver']['name']);
        $this->assertCount(1, $decoded['runs'][0]['results']);
        $this->assertSame('disenchant.it.magic_value.detected.v1', $decoded['runs'][0]['results'][0]['ruleId']);
    }
}
