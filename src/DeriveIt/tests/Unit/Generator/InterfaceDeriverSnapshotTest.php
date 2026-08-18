<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use ReflectionClass;

class InterfaceDeriverSnapshotTest extends Unit
{
    /**
     * @return iterable<string, array{0: class-string, 1: string}>
     */
    public function fixtureProvider(): iterable
    {
        $roots = [
            '_Fixture' => 'fixtures',
            '_Fixture\Stubs' => 'fixtures/Stubs',
        ];

        foreach ($roots as $namespace => $dir) {
            foreach ((array) glob($dir . '/*.php') as $file) {
                $shortName = basename($file, '.php');
                $class = '\\' . $namespace . '\\' . $shortName;

                if (!class_exists($class, true)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                if ($reflection->isInterface() || $reflection->isTrait() || $reflection->isEnum()) {
                    continue;
                }

                $golden = __DIR__ . '/../../_expected/' . $shortName . '.interface.php';

                if (!is_file($golden)) {
                    continue;
                }

                yield $shortName => [$class, $golden];
            }
        }
    }

    /**
     * @dataProvider fixtureProvider
     */
    public function testDerivationMatchesGoldenFile(string $class, string $golden): void
    {
        $source = DeriveIt::derive($class);

        verify($source)
            ->equals(file_get_contents($golden))
        ;
    }
}
