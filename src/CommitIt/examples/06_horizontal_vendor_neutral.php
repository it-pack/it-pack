<?php

declare(strict_types=1);

/*
 * Example 06 — HORIZONTAL: a vendor-neutral core (one function, any backend).
 *
 * The inspect() helper depends ONLY on the Repository port. It never names git,
 * czproject, or any adapter. The workflow that obtains the Repository differs
 * (init vs clone) but inspect() is byte-for-byte identical, which is what makes
 * the core swappable: a future adapter (e.g. Mercurial) implements the same
 * ports and inspect() needs no change.
 *
 * Run: php examples/06_horizontal_vendor_neutral.php
 */

require __DIR__ . '/bootstrap.php';

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Contract\Repository;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;

headline('Example 06 — Horizontal: a vendor-neutral core (one function, any backend)');

$inspect = function (Repository $repo): void {
    echo '  path:    ' . $repo->getPath() . "\n";
    echo '  branches: ' . formatBranches($repo->listBranches()) . "\n";
    echo '  last:    ' . ($repo->getLastCommit()?->subject ?? '(none)') . "\n";
};

$factory = new GitPhpRepositoryFactory();
$author = CommitOptions::default()->withAuthor('Aurora Bot', 'bot@aurora.dev');

step('Build a repository with init(), then inspect it through the port.');
$dirA = exampleTempDir('neutral-a');
$repoA = CommitIt::init(
    RepositoryPath::from($dirA),
    $factory,
    InitOptions::default()->withInitialBranch('main'),
);
file_put_contents($dirA . '/README.md', "# Aurora\n");
$repoA->stageAll();
$repoA->commit(CommitMessage::fromParts('Initial commit'), $author);
$inspect($repoA);

step('Obtain a SECOND repository via clone(); the SAME inspect() works unchanged.');
$dirB = exampleTempDir('neutral-b');
$repoB = CommitIt::clone($dirA, RepositoryPath::from($dirB), $factory);
$inspect($repoB);

success('Vendor-neutral: inspect() is identical regardless of how the repo was obtained.');
