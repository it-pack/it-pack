<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\JsonReportRecorder;
use ItPack\DisenchantIt\Factory\DisenchanterFactory;

final class DisenchanterDryRunTest extends Unit
{
    public function testDryRunReturnsUnchangedSourceAndRecordsPlan(): void
    {
        $recorder = new JsonReportRecorder();
        $disenchanter = DisenchanterFactory::create(recorder: $recorder)->build();

        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $result = $disenchanter->rewrite($code, 1, null, true);

        // No edit applied in dry-run.
        $this->assertSame($code, $result);

        // The plan occurrence carries the planned edits.
        $reports = $recorder->reports();
        $plan = null;
        foreach ($reports as $report) {
            if ($report['kind'] === 'disenchant.it.dry_run.plan.v1') {
                $plan = $report['payload'];
            }
        }

        $this->assertNotNull($plan, 'a dry_run.plan.v1 occurrence was recorded');
        $this->assertSame(1, $plan['count']);
        $this->assertSame('const', $plan['edits'][0]['kind']);
        $this->assertSame("'admin-user'", $plan['edits'][0]['replacements'][0]['needle']);
        $this->assertSame('self::ADMIN_USER', $plan['edits'][0]['replacements'][0]['replacement']);
    }

    public function testNonDryRunStillWritesAndDiffersFromDryRun(): void
    {
        $disenchanter = DisenchanterFactory::create()->build();

        $code = <<<'PHP'
            <?php
            class C {
                public function a(): string { return 'admin-user'; }
                public function b(): string { return 'admin-user'; }
            }
            PHP;

        $written = $disenchanter->rewrite($code);
        $this->assertStringContainsString('self::ADMIN_USER', $written);
        $this->assertStringNotContainsString("return 'admin-user';", $written);
    }
}
