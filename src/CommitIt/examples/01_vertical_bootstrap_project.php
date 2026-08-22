<?php

declare(strict_types=1);

/*
 * Example 01 — VERTICAL SLICE: bootstrap a project from nothing.
 *
 * Walks the full Repository port surface for a single repository:
 *   init -> stage -> commit -> createBranch -> checkout -> commit -> createTag
 * and reads the resulting state back. Shows immutable CommitOptions builders
 * and validation-first value objects in a realistic flow.
 *
 * Run: php examples/01_vertical_bootstrap_project.php
 */

require __DIR__ . '/bootstrap.php';

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use ItPack\CommitIt\SDK\CommitIt;

headline('Example 01 — Vertical slice: bootstrap a project from nothing');

$factory = new GitPhpRepositoryFactory();
$dir = exampleTempDir('bootstrap');

step('Initialize a fresh repository on branch "main".');
$repo = CommitIt::init(
    RepositoryPath::from($dir),
    $factory,
    InitOptions::default()->withInitialBranch('main'),
);
success('Repository created at ' . $dir);

$author = CommitOptions::default()->withAuthor('Aurora Bot', 'bot@aurora.dev');

step('Author the initial commit (identity supplied per-commit via CommitOptions).');
file_put_contents($dir . '/README.md', "# Aurora\n\nThe project that showcases CommitIt.\n");
$repo->stageAll();
$repo->commit(CommitMessage::fromParts('Add project README'), $author);
success('Committed: ' . $repo->getLastCommit()?->subject);

step('Keep working: a second commit on main.');
mkdir($dir . '/src', 0o755, true);
file_put_contents($dir . '/src/aurora.php', "<?php\n// business logic\n");
$repo->stageAll();
$repo->commit(CommitMessage::fromParts('Add Aurora core module'), $author);

step('Branch off to prepare a release.');
$repo->createBranch(BranchName::from('release/1.0'));
$repo->checkoutBranch(BranchName::from('release/1.0'));
file_put_contents($dir . '/CHANGELOG.md', "# 1.0.0\n- Initial release\n");
$repo->stageAll();
$repo->commit(CommitMessage::fromParts('Cut 1.0.0 changelog'), $author);

step('Tag the release.');
$repo->createTag(TagName::from('v1.0.0'));

step('Inspect the resulting repository state.');
echo '  branches: ' . formatBranches($repo->listBranches()) . "\n";
echo '  tags:     ' . formatBranches($repo->listTags()) . "\n";
echo '  last:     ' . ($repo->getLastCommit()?->subject ?? '(none)') . "\n";

success('Vertical bootstrap complete. Repo left at: ' . $dir);
