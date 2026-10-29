<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespacesExactly;
use Arkitect\RuleBuilders\Architecture\Architecture;
use Arkitect\Rules\Rule;

/**
 * phparkitect rules for it-pack/derive-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/DeriveIt/deptrac (archived, deleted)):
 *   Contract   → ~  (self only)
 *   Behavior   → ~  (self only)
 *   Resolver   → Contract, Behavior
 *   Reflection → Contract, Resolver, Behavior
 *   Telemetry  → Contract
 *   Generator  → Contract, Reflection, Resolver, Behavior
 *   Cli        → Contract, Reflection, Resolver, Generator, Behavior, Entry
 *   Entry      → Contract, Reflection, Resolver, Generator, Behavior  (root-level
 *                files: DeriveIt.php, DeriveItFactory.php in namespace ItPack\DeriveIt)
 *
 * The Entry layer collects root-level PHP files (src/*.php). The Architecture
 * DSL cannot express "root-level only", so Entry is enforced via
 * ResideInOneOfTheseNamespacesExactly + exact-FQCN forbid rules.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Contract')->definedBy('ItPack\DeriveIt\Contract\*')
        ->component('Behavior')->definedBy('ItPack\DeriveIt\Behavior\*')
        ->component('Resolver')->definedBy('ItPack\DeriveIt\Resolver\*')
        ->component('Reflection')->definedBy('ItPack\DeriveIt\Reflection\*')
        ->component('Telemetry')->definedBy('ItPack\DeriveIt\Telemetry\*')
        ->component('Generator')->definedBy('ItPack\DeriveIt\Generator\*')
        ->component('Cli')->definedBy('ItPack\DeriveIt\Cli\*')
        ->where('Contract')->mayDependOnComponents()
        ->where('Behavior')->mayDependOnComponents()
        ->where('Resolver')->mayDependOnComponents('Contract', 'Behavior')
        ->where('Reflection')->mayDependOnComponents('Contract', 'Resolver', 'Behavior')
        ->where('Telemetry')->mayDependOnComponents('Contract')
        ->where('Generator')->mayDependOnComponents('Contract', 'Reflection', 'Resolver', 'Behavior')
        ->where('Cli')->mayDependOnComponents('Contract', 'Reflection', 'Resolver', 'Generator', 'Behavior');

    $entryClasses = [
        'ItPack\DeriveIt\DeriveIt',
        'ItPack\DeriveIt\DeriveItFactory',
    ];

    $entryRules = [
        // Entry (root-level classes) may not depend on Cli.
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespacesExactly('ItPack\DeriveIt'))
            ->should(new NotDependsOnTheseNamespaces(['ItPack\DeriveIt\Cli']))
            ->because('Entry may depend on Contract, Reflection, Resolver, Generator, Behavior only (not Cli)'),

        // Only Cli may depend on Entry classes; all other non-Entry components are forbidden.
        Rule::namespace(
            'ItPack\DeriveIt\Contract',
            'ItPack\DeriveIt\Behavior',
            'ItPack\DeriveIt\Resolver',
            'ItPack\DeriveIt\Reflection',
            'ItPack\DeriveIt\Telemetry',
            'ItPack\DeriveIt\Generator'
        )->should(new NotDependsOnTheseNamespaces($entryClasses))
            ->because('Only Cli may depend on Entry classes'),
    ];

    $config->add(
        $classSet,
        ...$architecture->rules(),
        ...$entryRules,
    );
};
