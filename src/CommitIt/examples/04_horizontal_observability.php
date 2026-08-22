<?php

declare(strict_types=1);

/*
 * Example 04 — HORIZONTAL: observe every operation (RunIt-ready telemetry).
 *
 * Observation is orthogonal to the workflow: the same sequence runs unchanged
 * whether wrapped by a real RepositoryObserver (emitting start/success for every
 * operation) or by NullRepositoryObserver (zero overhead). The observer vocabulary
 * (operation name + structured context) is exactly what a future RunIt adapter
 * would map to its Activity/Observation/Occurrence triad.
 *
 * Run: php examples/04_horizontal_observability.php
 */

require __DIR__ . '/bootstrap.php';

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Application\RepositoryOpener;
use ItPack\CommitIt\Contract\NullRepositoryObserver;
use ItPack\CommitIt\Contract\RepositoryObserver;
use ItPack\CommitIt\Domain\Option\CommitOptions;
use ItPack\CommitIt\Domain\Option\InitOptions;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\Domain\Value\TagName;
use ItPack\CommitIt\SDK\CommitIt;

headline('Example 04 — Horizontal: observe every operation (RunIt-ready telemetry)');

$factory = new GitPhpRepositoryFactory();
$author = CommitOptions::default()->withAuthor('Aurora Bot', 'bot@aurora.dev');

$timeline = new class implements RepositoryObserver {
    public function onStart(string $operation, array $context = []): void
    {
        $extra = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        echo "  \033[0;36m→ start\033[0m  $operation$extra\n";
    }

    public function onSuccess(string $operation, array $context = []): void
    {
        echo "  \033[0;32m✓ done \033[0m  $operation\n";
    }

    public function onFailure(string $operation, \Throwable $error, array $context = []): void
    {
        echo "  \033[0;31m✗ fail \033[0m  $operation: {$error->getMessage()}\n";
    }
};

$runShowcase = function (RepositoryObserver $observer, string $workDir) use ($factory, $author): void {
    $repo = (new RepositoryOpener($factory, $observer))->init(
        RepositoryPath::from($workDir),
        InitOptions::default()->withInitialBranch('main'),
    );
    file_put_contents($workDir . '/README.md', "# Aurora\n");
    $repo->stageAll();
    $repo->commit(CommitMessage::fromParts('Initial commit'), $author);
    $repo->createBranch(BranchName::from('release/1.0'));
    $repo->createTag(TagName::from('v1.0.0'));
};

step('With a real observer, every operation emits start/success events.');
$runShowcase($timeline, exampleTempDir('observed-a'));

step('With NullRepositoryObserver the same code runs with zero observation overhead.');
$runShowcase(new NullRepositoryObserver(), exampleTempDir('observed-b'));

success('Observation is orthogonal: swap the observer, not the workflow.');
