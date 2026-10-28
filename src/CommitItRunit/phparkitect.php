<?php

declare(strict_types=1);

require_once __DIR__ . '/../../tools/qa/phparkitect/base.php';

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\RuleBuilders\Architecture\Architecture;

/**
 * phparkitect rules for it-pack/commit-it-runit (Phase 2.4).
 *
 * CommitItRunit is a bridge: CommitIt VCS operations → RunIt execution.
 * It is a sanctioned importer of BOTH CommitIt and RunIt.
 * Internal sub-namespaces: Model (execution-shaped data objects), Adapter (the
 * observer adapter itself). Single component: the package may depend on
 * CommitIt core and RunIt core.
 */
return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $architecture = Architecture::withComponents()
        ->component('Bridge')->definedBy('ItPack\CommitItRunit\*')
        ->where('Bridge')->mayDependOnComponents('Bridge', 'ItPack\CommitIt\*', 'ItPack\RunIt\*');

    $config->add($classSet, ...$architecture->rules());
};
