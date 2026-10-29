<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use function ItPack\Qa\PhpArkitect\layeredArchitecture;

/**
 * phparkitect rules for it-pack/commit-it (deptrac → phparkitect migration).
 *
 * Deptrac layers (src/CommitIt/deptrac (archived, deleted)):
 *   Common      → ~  (self only)
 *   Contract    → Common, Contract
 *   Domain      → Common, Contract  (includes src/Behavior/Domain/)
 *   Application → Common, Contract, Domain
 *   SDK         → Common, Contract, Domain, Application
 *   Adapter     → Common, Contract, Domain
 *
 * BehaviorDomain (src/Behavior/Domain/*.php) is part of the Domain layer in
 * deptrac; here it is a separate component that all Domain-allowing layers
 * also list as an allowed dependency.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $rules = layeredArchitecture(
        'ItPack\CommitIt',
        [
            'Common' => 'Common\*',
            'Contract' => 'Contract\*',
            'Domain' => 'Domain\*',
            'BehaviorDomain' => 'Behavior\Domain\*',
            'Application' => 'Application\*',
            'SDK' => 'SDK\*',
            'Adapter' => 'Adapter\*',
        ],
        [
            'Common' => [],
             'Contract' => ['Common', 'Domain', 'BehaviorDomain'],
            'Domain' => ['Common', 'Contract', 'BehaviorDomain'],
            'BehaviorDomain' => ['Common', 'Contract', 'Domain'],
            'Application' => ['Common', 'Contract', 'Domain', 'BehaviorDomain'],
            'SDK' => ['Common', 'Contract', 'Domain', 'BehaviorDomain', 'Application'],
            'Adapter' => ['Common', 'Contract', 'Domain', 'BehaviorDomain'],
        ]
    );

    $config->add($classSet, ...$rules);
};
