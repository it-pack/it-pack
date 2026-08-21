<?php

declare(strict_types=1);

namespace Tests\Integration\Tools\Dogfood;

use Codeception\Test\Unit;

/**
 * The benchmark subprocess must not load RunIt dogfooding, so measured hot paths
 * are not distorted by the observing process. This verifies the structural guarantee:
 * no benchmark file or phpbench configuration imports the dogfooding module, and the
 * dogfooding entry script does not bootstrap the benchmark suite.
 *
 * @group tools
 * @group dogfood
 * @group integration
 */
final class BenchmarkBootstrapUninstrumentedTest extends Unit
{
    private const string PACKAGE_ROOT = __DIR__ . '/../../../..';

    public function testBenchmarksDoNotImportDogfooding(): void
    {
        $discovered = glob(self::PACKAGE_ROOT . '/benchmarks/*.php');
        $benchmarkFiles = $discovered === false ? [] : $discovered;

        self::assertNotEmpty($benchmarkFiles, 'At least one benchmark file exists to scan.');

        foreach ($benchmarkFiles as $file) {
            $source = file_get_contents($file);

            self::assertIsString($source);
            self::assertStringNotContainsString(
                'Tools\\Dogfood',
                $source,
                sprintf('%s must not import the dogfooding module.', basename($file)),
            );
        }
    }

    public function testPhpbenchConfigDoesNotLoadDogfooding(): void
    {
        $config = file_get_contents(self::PACKAGE_ROOT . '/phpbench.json');

        self::assertIsString($config);
        self::assertStringNotContainsString(
            'Tools\\Dogfood',
            $config,
            'phpbench.json must not reference the dogfooding module.',
        );
    }

    public function testDogfoodEntryDoesNotBootstrapBenchmarks(): void
    {
        $entry = file_get_contents(self::PACKAGE_ROOT . '/tools/dogfood/run-dogfood.php');

        self::assertIsString($entry);
        self::assertStringNotContainsString(
            'Benchmarks\\',
            $entry,
            'The dogfooding entry script must not load the benchmark suite.',
        );
    }
}
