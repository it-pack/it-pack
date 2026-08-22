<?php

declare(strict_types=1);

/*
 * Example 05 — HORIZONTAL: defensive by design (validation + exception taxonomy).
 *
 * Demonstrates the two safety nets that protect every backend call:
 *   1. Validation-first value objects reject bad input at the boundary, before
 *      any git process is spawned.
 *   2. Failures surface through a precise exception taxonomy that is catchable
 *      both specifically (InvalidReferenceNameException) and generically
 *      (VcsOperationException marker).
 *
 * Run: php examples/05_horizontal_defense.php
 */

require __DIR__ . '/bootstrap.php';

use ItPack\CommitIt\Adapter\Git\GitPhpRepositoryFactory;
use ItPack\CommitIt\Contract\Exception\VcsOperationException as VcsOperationExceptionMarker;
use ItPack\CommitIt\Domain\Exception\InvalidCommitMessageException;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Exception\RepositoryNotFoundException;
use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\CommitMessage;
use ItPack\CommitIt\Domain\Value\RepositoryPath;
use ItPack\CommitIt\SDK\CommitIt;

headline('Example 05 — Horizontal: defensive by design (validation + taxonomy)');

step('Invalid reference names are rejected at the value-object boundary.');
foreach (['bad name', 'a..b', '~secret', 'origin/'] as $candidate) {
    try {
        BranchName::from($candidate);
        fail('accepted invalid name: ' . $candidate);
    } catch (InvalidReferenceNameException $e) {
        success('rejected ' . json_encode($candidate) . ' -> ' . $e->getMessage());
    }
}

step('Commit messages are validated before a commit is ever attempted.');
try {
    CommitMessage::fromParts("subject\nsecond line in subject");
    fail('accepted multi-line subject');
} catch (InvalidCommitMessageException $e) {
    success('rejected multi-line subject -> ' . $e->getMessage());
}

$factory = new GitPhpRepositoryFactory();
$nonRepo = exampleTempDir('does-not-exist') . '/missing';

step('The exception taxonomy is catchable both specifically and generically.');
try {
    CommitIt::open(RepositoryPath::from($nonRepo), $factory);
} catch (RepositoryNotFoundException $e) {
    success('caught specific: ' . $e::class);
    echo '  is VcsOperationException (generic family): '
        . ($e instanceof VcsOperationExceptionMarker ? 'yes' : 'no') . "\n";
}

step('A generic catch still works for any backend failure.');
try {
    CommitIt::open(RepositoryPath::from($nonRepo), $factory);
} catch (VcsOperationExceptionMarker $e) {
    success('caught via marker interface: ' . $e->getMessage());
}
