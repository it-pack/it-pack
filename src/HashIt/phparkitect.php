<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespacesExactly;
use Arkitect\Rules\Rule;

/**
 * phparkitect rules for it-pack/hash-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/HashIt/deptrac.archive.yaml) use classNameRegex:
 *   Core   → ~  (depends on nothing but itself)
 *   Format → [Core]
 *   Adapter (excl NativeHashIt) → [Core]
 *   CompositionRoot (NativeHashIt) → [Core, Adapter]
 *
 * Core is "everything in ItPack\HashIt\* except Format/Adapter" — including
 * root-level classes. phparkitect cannot express negative namespace globs, so
 * we list each Core sub-namespace explicitly and use
 * ResideInOneOfTheseNamespacesExactly for root-level classes.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $coreNamespaces = [
        'ItPack\HashIt\AlgorithmSource',
        'ItPack\HashIt\Contract',
        'ItPack\HashIt\Factory',
        'ItPack\HashIt\HashInput',
        'ItPack\HashIt\KeyDerivation',
        'ItPack\HashIt\Observability',
    ];

    $config->add(
        $classSet,

        // Core (sub-namespaces) must not depend on Format or Adapter.
        Rule::namespace(...$coreNamespaces)
            ->should(new NotDependsOnTheseNamespaces(['ItPack\HashIt\Format', 'ItPack\HashIt\Adapter']))
            ->because('Core depends on nothing but itself'),

        // Root-level Core classes (namespace = ItPack\HashIt) must not depend
        // on Format or Adapter.
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespacesExactly('ItPack\HashIt'))
            ->should(new NotDependsOnTheseNamespaces(['ItPack\HashIt\Format', 'ItPack\HashIt\Adapter']))
            ->because('Core depends on nothing but itself'),

        // Format must not depend on Adapter.
        Rule::namespace('ItPack\HashIt\Format')
            ->should(new NotDependsOnTheseNamespaces(['ItPack\HashIt\Adapter']))
            ->because('Format may only depend on Core'),

        // Adapter (incl NativeHashIt) must not depend on Format.
        Rule::namespace('ItPack\HashIt\Adapter')
            ->should(new NotDependsOnTheseNamespaces(['ItPack\HashIt\Format']))
            ->because('Adapter may only depend on Core and, for NativeHashIt, other Adapters'),
    );
};
