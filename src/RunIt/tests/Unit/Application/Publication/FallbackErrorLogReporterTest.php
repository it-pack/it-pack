<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\FallbackErrorLogReporter;
use LogicException;
use RuntimeException;

/**
 * @group application
 * @group publication
 */
final class FallbackErrorLogReporterTest extends Unit
{
    public function testReportedLineIncludesArtifactIdSeparatedByLeadingSpace(): void
    {
        $logged = null;

        $reporter = new FallbackErrorLogReporter(
            logTo: static function (string $line) use (&$logged): void {
                $logged = $line;
            },
        );

        $reporter->report(
            new RuntimeException('primary boom'),
            new LogicException('handler fault'),
            'artifact-42',
        );

        self::assertSame(
            '[runit] publication health fallback:  handler fault [LogicException] handler fault while reporting [RuntimeException] primary boom artifact=artifact-42',
            $logged,
            'the artifact id must be appended to the preceding message with a leading space separator',
        );
    }

    public function testReportedLineOmitsArtifactSegmentWhenNoArtifactId(): void
    {
        $logged = null;

        $reporter = new FallbackErrorLogReporter(
            logTo: static function (string $line) use (&$logged): void {
                $logged = $line;
            },
        );

        $reporter->report(
            new RuntimeException('primary boom'),
            new LogicException('handler fault'),
        );

        self::assertSame(
            '[runit] publication health fallback:  handler fault [LogicException] handler fault while reporting [RuntimeException] primary boom',
            $logged,
        );
    }
}
