<?php

declare(strict_types=1);

namespace Tests\Functional;

use Codeception\Test\Unit;
use ItPack\DeriveIt\DeriveIt;
use Nette\PhpGenerator\ClassType;
use ReflectionClass;

/**
 * Integration smoke test: derive interfaces from real classes shipped by a
 * dependency (nette/php-generator) and prove the output is valid, self-contained
 * PHP that matches the real adaptee surface.
 */
class DeriveRealPackageTest extends Unit
{
    private static array $loaded = [];

    private function load(string $source): void
    {
        $hash = md5($source);
        if (isset(self::$loaded[$hash])) {
            return;
        }

        $dir = sys_get_temp_dir() . '/deriveit_functional';
        @mkdir($dir);
        $file = $dir . '/' . $hash . '.php';
        file_put_contents($file, $source);

        require $file;
        self::$loaded[$hash] = true;
    }

    public function testDerivesInterfaceForRealNetteClass(): void
    {
        $source = DeriveIt::derive(ClassType::class);

        verify($source)->stringContainsString('interface ClassTypeInterface');
        verify($source)->stringContainsString('function getName()');

        $this->load($source);
        /** @var string $interface */
        $interface = 'Nette\\PhpGenerator\\ClassTypeInterface';

        verify(interface_exists($interface, false))->true();

        $reflection = new ReflectionClass($interface);
        verify($reflection->hasMethod('getName'))->true();
    }

    public function testDerivedInterfaceIsSelfContainedForRealClass(): void
    {
        $source = DeriveIt::derive(ClassType::class);

        // flat derivation must not extend anything (no adaptee leakage)
        verify(str_contains($source, 'extends'))->false();
    }
}
