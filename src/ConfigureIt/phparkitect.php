<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use function ItPack\Qa\PhpArkitect\layeredArchitecture;

/**
 * phparkitect rules for it-pack/configure-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/ConfigureIt/deptrac.archive.yaml) — all directory-based:
 *   Contract     → Contract, ValueObject
 *   ValueObject  → ValueObject, Contract
 *   Core         → Core, Contract, ValueObject, Cache, Execution
 *   Execution    → Execution, Contract, ValueObject
 *   Cache        → Cache, Contract, Core, Adapter
 *   Adapter      → Adapter, Contract, Core, Execution, ValueObject
 *   Bridge       → Bridge, Contract, Core, Execution, ValueObject
 *   Codegen      → Codegen, Contract, Adapter
 *   CLI          → CLI, Contract, Core, Adapter, ValueObject
 *   Wiring       → Wiring, Contract, Core, Adapter, Bridge, Execution, ValueObject, Cache
 *
 * Exception/ directory is intentionally uncovered (same as deptrac).
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $rules = layeredArchitecture(
        'ItPack\ConfigureIt',
        [
            'Contract' => 'Contract\*',
            'ValueObject' => 'ValueObject\*',
            'Core' => 'Core\*',
            'Execution' => 'Execution\*',
            'Cache' => 'Cache\*',
            'Adapter' => 'Adapter\*',
            'Bridge' => 'Bridge\*',
            'Codegen' => 'Codegen\*',
            'CLI' => 'CLI\*',
            'Wiring' => 'Wiring\*',
        ],
        [
            'Contract' => ['ValueObject'],
            'ValueObject' => ['Contract'],
            'Core' => ['Contract', 'ValueObject', 'Cache', 'Execution'],
            'Execution' => ['Contract', 'ValueObject'],
            'Cache' => ['Contract', 'Core', 'Adapter'],
            'Adapter' => ['Contract', 'Core', 'Execution', 'ValueObject'],
            'Bridge' => ['Contract', 'Core', 'Execution', 'ValueObject'],
            'Codegen' => ['Contract', 'Adapter'],
            'CLI' => ['Contract', 'Core', 'Adapter', 'ValueObject'],
            'Wiring' => ['Contract', 'Core', 'Adapter', 'Bridge', 'Execution', 'ValueObject', 'Cache'],
        ]
    );

    $config->add($classSet, ...$rules);
};
