<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Observation;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ObservationSeverity::class)]
final class ObservationSeverityTest extends Unit
{
    #[DataProvider('levels')]
    public function testLevel(ObservationSeverity $severity, int $expected): void
    {
        self::assertSame($expected, $severity->level());
    }

    /**
     * @return array<string, array{ObservationSeverity, int}>
     */
    public static function levels(): array
    {
        return [
            'trace' => [ObservationSeverity::Trace, 50],
            'debug' => [ObservationSeverity::Debug, 100],
            'info' => [ObservationSeverity::Info, 200],
            'notice' => [ObservationSeverity::Notice, 250],
            'warning' => [ObservationSeverity::Warning, 300],
            'error' => [ObservationSeverity::Error, 400],
            'critical' => [ObservationSeverity::Critical, 500],
            'alert' => [ObservationSeverity::Alert, 550],
            'emergency' => [ObservationSeverity::Emergency, 600],
        ];
    }

    #[DataProvider('errorFlags')]
    public function testIsError(ObservationSeverity $severity, bool $expected): void
    {
        self::assertSame($expected, $severity->isError());
    }

    /**
     * @return array<string, array{ObservationSeverity, bool}>
     */
    public static function errorFlags(): array
    {
        return [
            'below error' => [ObservationSeverity::Warning, false],
            'error' => [ObservationSeverity::Error, true],
            'critical' => [ObservationSeverity::Critical, true],
            'alert' => [ObservationSeverity::Alert, true],
            'emergency' => [ObservationSeverity::Emergency, true],
        ];
    }

    public function testLabelReturnsValue(): void
    {
        self::assertSame('error', ObservationSeverity::Error->label());
    }
}
