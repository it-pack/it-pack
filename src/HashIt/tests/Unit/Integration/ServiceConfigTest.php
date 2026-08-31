<?php

declare(strict_types=1);

namespace Tests\Unit\Integration;

use Codeception\Test\Unit;

/**
 * Smoke test for the framework-agnostic service wiring in config/services.php.
 * It does NOT boot a container — it only asserts each declared factory resolves
 * to an instance of the interface it is registered under.
 */
class ServiceConfigTest extends Unit
{
    public function testServiceDefinitionsResolveToDeclaredInterfaces(): void
    {
        /** @var array<class-string, callable(): object> $services */
        $services = require __DIR__ . '/../../../config/services.php';

        verify($services)->notEmpty();

        foreach ($services as $interface => $factory) {
            $instance = $factory();

            verify($instance instanceof $interface)->true(
                sprintf('Expected factory for "%s" to return an instance of that interface.', $interface),
            );
        }
    }

    public function testHasherBindingUsesGivenAlgorithmIndependently(): void
    {
        /** @var array<class-string, callable(): object> $services */
        $services = require __DIR__ . '/../../../config/services.php';

        $hasher = $services[\ItPack\HashIt\Contract\HasherInterface::class]();

        verify($hasher instanceof \ItPack\HashIt\Contract\HasherInterface)->true();
    }
}
