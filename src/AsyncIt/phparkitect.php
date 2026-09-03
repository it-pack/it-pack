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
 * phparkitect rules for it-pack/async-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/AsyncIt/deptrac.archive.yaml):
 *   Common       → ~
 *   Contract     → [Common]  (excludes Contract\Port\*)
 *   Port          → [Common, Contract, Domain]
 *   Domain        → [Common, Contract, Port]
 *   Application   → [Common, Contract, Port, Domain]
 *   SDK           → [Common, Contract, Port, Domain, Application]
 *   Adapter       → [Common, Contract, Port, Domain]
 *   Test Support  → [all]
 *
 * The deptrac Contract collector uses a negative-lookahead regex
 * `src/Contract/(?!Port/).*` so Port classes belong to a separate layer.
 * phparkitect cannot express negative namespace globs, so:
 *   - Port is its own component with selector `Contract\Port\*`.
 *   - Contract (excl Port) is enforced via a separate Rule using
 *     `ResideInOneOfTheseNamespacesExactly('ItPack\AsyncIt\Contract')`,
 *     which matches only classes whose namespace is exactly `Contract`
 *     (the directory has no sub-namespaces other than Port).
 *   - Contract is not a component in the Architecture DSL, so Contract
 *     dependencies are not forbidden by any component rule — matching
 *     deptrac's allow-all-unless-forbidden semantics.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Common')->definedBy('ItPack\AsyncIt\Common\*')
        ->component('Domain')->definedBy('ItPack\AsyncIt\Domain\*')
        ->component('Port')->definedBy('ItPack\AsyncIt\Contract\Port\*')
        ->component('Application')->definedBy('ItPack\AsyncIt\Application\*')
        ->component('SDK')->definedBy('ItPack\AsyncIt\SDK\*')
        ->component('Adapter')->definedBy('ItPack\AsyncIt\Adapter\*')
        ->component('Conformance')->definedBy('ItPack\AsyncIt\Conformance\*')
        ->where('Common')->mayDependOnComponents()
        ->where('Domain')->mayDependOnComponents('Common', 'Port')
        ->where('Port')->mayDependOnComponents('Common', 'Domain')
        ->where('Application')->mayDependOnComponents('Common', 'Domain', 'Port')
        ->where('SDK')->mayDependOnComponents('Common', 'Domain', 'Port', 'Application')
        ->where('Adapter')->mayDependOnComponents('Common', 'Domain', 'Port')
        ->where('Conformance')->mayDependOnComponents('Common', 'Domain', 'Port', 'Application', 'SDK', 'Adapter');

    $rules = array_merge(
        iterator_to_array($architecture->rules()),
        [
            // Contract (excl Port) must not depend on Port or any other layer
            // (except Common). In deptrac, the Contract collector uses
            // src/Contract/(?!Port/).* to exclude the Port sub-namespace.
            Rule::allClasses()
                ->that(new ResideInOneOfTheseNamespacesExactly('ItPack\AsyncIt\Contract'))
                ->should(new NotDependsOnTheseNamespaces([
                    'ItPack\AsyncIt\Contract\Port',
                    'ItPack\AsyncIt\Domain',
                    'ItPack\AsyncIt\Application',
                    'ItPack\AsyncIt\SDK',
                    'ItPack\AsyncIt\Adapter',
                    'ItPack\AsyncIt\Conformance',
                ]))
                ->because('Contract must not depend on Port or other layers (except Common)'),
        ]
    );

    $config->add(
        $classSet,
        ...$rules,
    );
};
