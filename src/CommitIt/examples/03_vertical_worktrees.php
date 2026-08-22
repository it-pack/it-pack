<?php

declare(strict_types=1);

/*
 * Example 03 — VERTICAL SLICE: parallel work with git worktrees.
 *
 * Uses the WorkingTree port to spin up two independent checkouts (feature and
 * hotfix) from one repository, without cloning. Shows that branches are shared
 * across worktrees while each keeps its own working copy, and that a worktree
 * is itself just another Repository you can reopen and commit into.
 *
 * Run: php examples/03_vertical_worktrees.php
 */

require __DIR__ . '/bootstrap.php';

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;

headline('Example 03 — Vertical slice: parallel work with git worktrees');

$factory = new GitPhpRepositoryFactory();
$mainDir = exampleTempDir('worktree-main');
$featureDir = exampleTempDir('worktree-feature');
$hotfixDir = exampleTempDir('worktree-hotfix');

$author = CommitOptions::default()->withAuthor('Aurora Bot', 'bot@aurora.dev');

$repo = CommitIt::init(
    RepositoryPath::from($mainDir),
    $factory,
    InitOptions::default()->withInitialBranch('main'),
);
file_put_contents($mainDir . '/README.md', "# Aurora\n");
$repo->stageAll();
$repo->commit(CommitMessage::fromParts('Initial commit'), $author);

step('Open two worktrees for parallel lines of work (no separate clones).');
$tree = $repo->getWorkingTree();
$tree->add(RepositoryPath::from($featureDir), BranchName::from('feature/x'));
$tree->add(RepositoryPath::from($hotfixDir), BranchName::from('hotfix/y'));
success('Worktrees created.');

step('Each worktree is an independent checkout on its own branch.');
foreach ($tree->listWorktrees() as $info) {
    echo '  ' . ($info->isMain ? 'main ' : '     ')
        . $info->path . '  [' . ($info->branch ?? 'detached') . "]\n";
}

step('Work inside the feature worktree without touching the main checkout.');
file_put_contents($featureDir . '/feature.php', "<?php\n// feature work\n");
$featureRepo = CommitIt::open(RepositoryPath::from($featureDir), $factory);
$featureRepo->stageAll();
$featureRepo->commit(CommitMessage::fromParts('Implement feature x'), $author);
success('Committed in feature worktree; main checkout untouched.');

step('Branches are shared across worktrees; the checked-out tree differs.');
echo '  main repo branches:    ' . formatBranches($repo->listBranches()) . "\n";
echo '  feature worktree sees:  ' . formatBranches($featureRepo->listBranches()) . "\n";

success('Worktree demo complete. Main repo at ' . $mainDir);
