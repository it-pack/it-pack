<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\RuleBuilders\Architecture\Architecture;
use Arkitect\Rules\Rule;
use function ItPack\Qa\PhpArkitect\forbidSibling;

/**
 * phparkitect rules for it-pack/command-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/CommandIt/deptrac (archived, deleted)):
 *   Contract      → Domain (hexagonal inward)
 *   Domain        → Contract
 *   Port           → Contract  (subsumed by Contract in deptrac: Contract collector
 *                  claims Port classes first; Port layer is effectively empty)
 *   Application   → Contract, Domain, Port
 *   SDK            → Contract, Domain, Application, Port
 *   Adapter        → Contract, Domain, Port  (excludes src/Adapter/CLI/)
 *   Bootstrap      → Contract, Domain, Application, SDK, Adapter, Port
 *   SymfonyConsole  → Contract, Domain, Port  (src/Adapter/CLI/ + Symfony Console vendor)
 *   RunIt           → FORBIDDEN everywhere (ports-not-imports doctrine)
 *
 * Translation notes:
 *   - Port (ItPack\CommandIt\Contract\Port\*) is a sub-namespace of Contract.
 *     In deptrac, the Contract layer's classNameRegex claims Port classes first,
 *     so the Port layer is empty. In phparkitect, Contract's selector
 *     ('ItPack\CommandIt\Contract\*') also matches Port; the Architecture DSL
 *     applies the Contract rule to them (which is a superset of the deptrac Port
 *     rule). Since no Contract class depends on Port per deptrac, this is safe.
 *   - BehaviorDomain (ItPack\CommandIt\Behavior\Domain\*) is part of the Domain
 *     layer in deptrac; here it is a separate component that all Domain-allowing
 *     layers also allow.
 *   - CLI adapter classes are in the Adapter namespace but belong to the
 *     "SymfonyConsole" deptrac layer. An extra rule (SymfonyConsole rule below)
 *     enforces that they follow the stricter layer.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Common')->definedBy('ItPack\CommandIt\Common\*')
        ->component('Contract')->definedBy('ItPack\CommandIt\Contract\*')
        ->component('Domain')->definedBy('ItPack\CommandIt\Domain\*')
        ->component('BehaviorDomain')->definedBy('ItPack\CommandIt\Behavior\Domain\*')
        ->component('Application')->definedBy('ItPack\CommandIt\Application\*')
        ->component('SDK')->definedBy('ItPack\CommandIt\SDK\*')
        ->component('Adapter')->definedBy('ItPack\CommandIt\Adapter\*')
        ->component('Bootstrap')->definedBy('ItPack\CommandIt\Bootstrap\*')
        ->where('Common')->mayDependOnComponents()
        ->where('Contract')->mayDependOnComponents('Domain', 'BehaviorDomain')
        ->where('Domain')->mayDependOnComponents('Contract', 'BehaviorDomain')
        ->where('BehaviorDomain')->mayDependOnComponents('Contract', 'Domain')
        ->where('Application')->mayDependOnComponents('Contract', 'Domain', 'BehaviorDomain')
        ->where('SDK')->mayDependOnComponents('Contract', 'Domain', 'BehaviorDomain', 'Application')
        ->where('Adapter')->mayDependOnComponents('Contract', 'Domain', 'BehaviorDomain')
        ->where('Bootstrap')->mayDependOnComponents('Contract', 'Domain', 'BehaviorDomain', 'Application', 'SDK', 'Adapter');

    // ---------------------------------------------------------------------
    // RunIt is forbidden everywhere — any direct import is a violation.
    // CommandIt must remain RunIt-agnostic; integration via future bridge.
    // ---------------------------------------------------------------------
    $runItForbidRule = forbidSibling(
        'ItPack\CommandIt',
        'ItPack\RunIt\*',
        'CommandIt must not depend on RunIt; integration is via the future it-pack/command-it-run-it bridge (ADR-0001)',
    );

    // ---------------------------------------------------------------------
    // Symfony Console vendor classes must not be imported by core layers.
    // Only the CLI adapter (SymfonyConsole deptrac layer) may use them.
    // ---------------------------------------------------------------------
    $symfonyConsoleForbidRule = Rule::namespace(
        'ItPack\CommandIt\Contract',
        'ItPack\CommandIt\Domain',
        'ItPack\CommandIt\Behavior\Domain',
        'ItPack\CommandIt\Application',
        'ItPack\CommandIt\SDK',
        'ItPack\CommandIt\Bootstrap'
    )->should(new NotDependsOnTheseNamespaces(['Symfony\Component\Console']))
        ->because('Symfony Console is an adapter concern; core must not import it directly');

    // ---------------------------------------------------------------------
    // CLI adapter (belongs to "SymfonyConsole" layer in deptrac) must not
    // depend on other Adapter sub-namespaces — it follows the stricter
    // SymfonyConsole layer rules, not the broad Adapter rules.
    // ---------------------------------------------------------------------
    $cliForbidRule = Rule::namespace('ItPack\CommandIt\Adapter\CLI')
        ->should(new NotDependsOnTheseNamespaces(
            ['ItPack\CommandIt\Adapter'],
            ['ItPack\CommandIt\Adapter\CLI']
        ))
        ->because('CLI/SymfonyConsole layer may not depend on other adapters');

    $rules = array_merge(
        iterator_to_array($architecture->rules()),
        [$runItForbidRule, $symfonyConsoleForbidRule, $cliForbidRule]
    );

    $config->add($classSet, ...$rules);
};
