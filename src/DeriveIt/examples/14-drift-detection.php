<?php

declare(strict_types=1);

/*
 * 14 — Keep ports honest: drift detection in CI.
 *
 * Derivation is deterministic, so a port derived from an unchanged adaptee reproduces
 * byte-for-byte. DeriveIt::diff() compares a freshly derived port against the one you
 * committed and reports whether they diverged — plus a unified diff. Wire it into CI
 * and an upstream change becomes a reviewable signal instead of a production surprise.
 */

require __DIR__ . '/bootstrap.php';

use ItPack\DeriveIt\DeriveIt;

$committed = DeriveIt::derive(_Fixture\Service::class);

$clean = DeriveIt::diff(_Fixture\Service::class, $committed);
echo "\033[1;33mSame source, no drift:\033[0m " . ($clean->drift ? 'DRIFT' : 'clean') . "\n";

$mutated = $committed . "\n// someone hand-edited the committed port\n";
$drifted = DeriveIt::diff(_Fixture\Service::class, $mutated);
echo "\033[1;33mHand-edited port:\033[0m " . ($drifted->drift ? "drift detected\n" . $drifted->diff : 'clean') . "\n";
