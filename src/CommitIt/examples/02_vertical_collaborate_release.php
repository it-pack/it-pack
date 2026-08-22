<?php

declare(strict_types=1);

/*
 * Example 02 — VERTICAL SLICE: collaborate and publish a release.
 *
 * Exercises the distributed workflow across three repositories backed by one
 * "upstream" bare repository acting as the shared remote:
 *   - developer seeds the project and pushes main
 *   - contributor clones, branches, commits, and pushes a release branch
 *   - remotes are read back through the port
 *
 * Run: php examples/02_vertical_collaborate_release.php
 */

require __DIR__ . '/bootstrap.php';

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Option\CloneOptions;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RemoteName;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;

headline('Example 02 — Vertical slice: collaborate and publish a release');

$factory = new GitPhpRepositoryFactory();
$upstreamDir = exampleTempDir('upstream');
$devDir = exampleTempDir('dev');
$cloneDir = exampleTempDir('contributor');

$author = CommitOptions::default()->withAuthor('Ada Dev', 'ada@aurora.dev');

step('Create the "upstream" bare repository (acts as the shared remote).');
CommitIt::init(
    RepositoryPath::from($upstreamDir),
    $factory,
    InitOptions::default()->withBare(),
)->execute('symbolic-ref', ['HEAD', 'refs/heads/main']);
success('Bare remote ready at ' . $upstreamDir);

step('Developer seeds the project and pushes main.');
$dev = CommitIt::init(
    RepositoryPath::from($devDir),
    $factory,
    InitOptions::default()->withInitialBranch('main'),
);
file_put_contents($devDir . '/README.md', "# Aurora\n");
$dev->stageAll();
$dev->commit(CommitMessage::fromParts('Initial commit'), $author);
$dev->addRemote(RemoteName::from('origin'), $upstreamDir);
$dev->push(RemoteName::from('origin'), BranchName::from('main'));
success('Pushed main to origin.');

step('Contributor clones the upstream repository.');
$contributor = CommitIt::clone(
    $upstreamDir,
    RepositoryPath::from($cloneDir),
    $factory,
    CloneOptions::default(),
);
success('Cloned into ' . $cloneDir);
echo '  cloned branches: ' . formatBranches($contributor->listBranches()) . "\n";

step('Contributor branches, works, and publishes the release branch.');
$contributor->createBranch(BranchName::from('release/1.0'));
$contributor->checkoutBranch(BranchName::from('release/1.0'));
file_put_contents($cloneDir . '/CHANGELOG.md', "# 1.0.0\n");
$contributor->stageAll();
$contributor->commit(CommitMessage::fromParts('Add 1.0.0 changelog'), $author);
$contributor->push(RemoteName::from('origin'), BranchName::from('release/1.0'));
success('Pushed release/1.0 to origin.');

step('Verify the published remote now exposes both branches.');
foreach ($contributor->listRemotes() as $name => $url) {
    echo '  remote ' . $name . ' -> ' . $url . "\n";
}

success('Collaboration flow complete. Upstream at ' . $upstreamDir);
