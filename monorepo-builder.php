<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\Config\MBConfig;
use Symplify\MonorepoBuilder\ValueObject\Option;

return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([
        'src/RunIt',
        'src/ConfigureIt',
        'src/ConfigureItPsr11',
        'src/ConfigureItSymfony',
        'src/ConfigureItRunit',
        'src/ConfigureItTesting',
        'src/ConfigureItSchemaDocs',
        'src/ConfigureItExplorer',
        'src/CommandIt',
        'src/CommitIt',
        'src/CommitItRunit',
        'src/DisenchantIt',
        'src/DeriveIt',
        'src/DeriveItRunit',
        'src/HashIt',
        'src/HashItAsync',
        'src/AsyncIt',
        'src/RunItThirdPartySample',
        'src/LazeIt',
        'src/FileIt',
        'src/ExtendIt',
    ]);

    $mbConfig->defaultBranch('main');

    $mbConfig->dataToAppend([
        'replace' => [
            'it-pack/run-it' => 'self.version',
            'it-pack/configure-it' => 'self.version',
            'it-pack/configure-it-psr11' => 'self.version',
            'it-pack/configure-it-symfony' => 'self.version',
            'it-pack/configure-it-runit' => 'self.version',
            'it-pack/configure-it-testing' => 'self.version',
            'it-pack/configure-it-schema-docs' => 'self.version',
            'it-pack/configure-it-explorer' => 'self.version',
            'it-pack/command-it' => 'self.version',
            'it-pack/commit-it' => 'self.version',
            'it-pack/commit-it-runit' => 'self.version',
            'it-pack/disenchant-it' => 'self.version',
            'it-pack/derive-it' => 'self.version',
            'it-pack/derive-it-runit' => 'self.version',
            'it-pack/hash-it' => 'self.version',
            'it-pack/hash-it-async' => 'self.version',
            'it-pack/async-it' => 'self.version',
            'it-pack/run-it-third-party-sample' => 'self.version',
            'it-pack/laze-it' => 'self.version',
            'it-pack/file-it' => 'self.version',
            'it-pack/extend-it' => 'self.version',
        ],
    ]);

    $mbConfig->dataToRemove([
        'repositories' => ['path'],
    ]);

    $parameters = $mbConfig->parameters();
    $parameters->set(Option::EXCLUDE_PACKAGE_VERSION_CONFLICTS, [
        'php',
        'codeception/codeception',
        'codeception/module-asserts',
        'codeception/module-phpbrowser',
        'infection/codeception-adapter',
        'open-telemetry/api',
    ]);
};
