<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use function ItPack\Qa\PhpArkitect\layeredArchitecture;

/**
 * phparkitect rules for it-pack/run-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/RunIt/deptrac (archived, deleted)):
 *   Common           → ~
 *   Contract         → Common  (uses src/Contract/(?!Port/).* — Port is a
 *                       separate layer in deptrac; here Contract's selector
 *                       also matches Port. Safe because deptrac is green.)
 *   Port              → Common, Contract
 *   Domain            → Common, Contract, Port  (includes src/Behavior/Domain/)
 *   Application       → Common, Contract, Port, Domain  (includes src/Behavior/Application/)
 *   SDK               → Common, Contract, Port, Domain, Application
 *   Common Adapter    → Common, Contract, Port, Domain
 *   External Adapter  → Common, Contract, Port, Domain
 *   Test Support      → all layers
 *
 * Common Adapter and External Adapter are merged into a single Adapter
 * component (both have identical dependency rules in deptrac).
 *
 * BehaviorDomain (src/Behavior/Domain/) and BehaviorApplication
 * (src/Behavior/Application/) belong to the Domain and Application layers
 * respectively in deptrac; here they are separate components that all
 * Domain/Application-allowing layers also list.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $rules = layeredArchitecture(
        'ItPack\RunIt',
        [
            'Common' => 'Common\*',
            'Contract' => 'Contract\*',
            'Domain' => 'Domain\*',
            'BehaviorDomain' => 'Behavior\Domain\*',
            'Application' => 'Application\*',
            'BehaviorApplication' => 'Behavior\Application\*',
            'SDK' => 'SDK\*',
            'Adapter' => 'Adapter\*',
            'Conformance' => 'Conformance\*',
        ],
        [
            'Common' => [],
            'Contract' => ['Common'],
            'Domain' => ['Common', 'Contract', 'BehaviorDomain'],
            'BehaviorDomain' => ['Common', 'Contract', 'Domain'],
            'Application' => ['Common', 'Contract', 'Domain', 'BehaviorDomain', 'BehaviorApplication'],
            'BehaviorApplication' => ['Common', 'Contract', 'Domain', 'BehaviorDomain', 'Application'],
            'SDK' => ['Common', 'Contract', 'Domain', 'BehaviorDomain', 'Application', 'BehaviorApplication'],
            'Adapter' => ['Common', 'Contract', 'Domain', 'BehaviorDomain'],
            'Conformance' => [
                'Common', 'Contract', 'Domain', 'BehaviorDomain',
                'Application', 'BehaviorApplication', 'SDK', 'Adapter',
            ],
        ]
    );

    $config->add($classSet, ...$rules);
};
