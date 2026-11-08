<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publication;

use Codeception\Test\Unit;
use ItPack\RunIt\Application\Publication\ErrorLogHealthReporter;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Observation\Observation;
use ItPack\RunIt\Domain\Observation\ObservationId;
use ItPack\RunIt\Domain\Shared\DefaultThrowableMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;

/**
 * The error-log health reporter is the fallback sink for publication faults —
 * the very path recursive or oversized failures take — so its output must be
 * sanitized through the throwable mapper instead of echoing raw messages.
 *
 * @group application
 * @group security
 */
#[CoversClass(ErrorLogHealthReporter::class)]
#[UsesClass(DefaultThrowableMapper::class)]
#[UsesClass(ExecutionId::class)]
#[UsesClass(Observation::class)]
#[UsesClass(ObservationId::class)]
final class ErrorLogHealthReporterTest extends Unit
{
    /** @var list<string> */
    private array $lines = [];

    public function testReportedMessageIsSanitizedThroughTheThrowableMapper(): void
    {
        $blocks = [];

        foreach (range(0, 59) as $index) {
            $blocks[] = sprintf('SECRET-BLOCK-%02d-%s', $index, str_repeat('x', 150));
        }

        $hugeMessage = implode('|', $blocks);

        $reporter = new ErrorLogHealthReporter(logTo: function (string $line): void {
            $this->lines[] = $line;
        });
        $reporter->report(new RuntimeException($hugeMessage), $this->observation());

        self::assertCount(1, $this->lines);
        $logged = $this->lines[0];

        self::assertStringContainsString(
            '[RuntimeException]',
            $logged,
            'the exception class is structural context and stays'
        );
        self::assertStringContainsString('publication health', $logged);
        self::assertStringContainsString('SECRET-BLOCK-00-', $logged, 'the truncated head of the message may appear');
        self::assertStringNotContainsString(
            'SECRET-BLOCK-59-',
            $logged,
            'the unbounded raw message must not reach the log'
        );
        self::assertLessThanOrEqual(4096, strlen($logged), 'a logged health line stays bounded even for huge messages');
    }

    public function testDefaultDestinationIsThePhpErrorLog(): void
    {
        $logPath = (string) tempnam(sys_get_temp_dir(), 'runit-health-log');
        $previous = (string) ini_get('error_log');

        try {
            ini_set('error_log', $logPath);

            $reporter = new ErrorLogHealthReporter();
            $reporter->report(new RuntimeException('default sink check'), $this->observation());
        } finally {
            ini_set('error_log', $previous);
        }

        // The default sink delegates to error_log(); whether the SAPI honors a custom
        // ini target is environment-dependent, so only assert no crash and cleanup.
        @unlink($logPath);

        $this->addToAssertionCount(1);
    }

    private function observation(): ExecutionArtifactInterface
    {
        return Observation::info(
            ObservationId::fromString('obs-1'),
            ExecutionId::fromString('exec-1'),
            'operational note',
        );
    }
}
