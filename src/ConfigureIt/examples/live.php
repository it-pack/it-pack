<?php

declare(strict_types=1);

use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Adapter\PollingWatcher;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ReloadableConfiguration;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

/**
 * M6 — Epic E4.1 (Live configuration: Watcher + ReloadableConfiguration) example.
 *
 * Demonstrates a live configuration facade that re-resolves and atomically
 * swaps its immutable snapshot when a watched source changes:
 *   - PollingWatcher      : portable change detection (polls a file signature)
 *   - FsWatcher           : kernel-level inotify watcher (production, Linux)
 *   - ReloadableConfiguration: holds the current snapshot, subscribes to the
 *                             watcher, and reloads on change.
 *
 * The runnable part below edits the config file on disk and drives the watcher
 * via a single {@see PollingWatcher::tick()} so the example terminates; in a
 * long-running worker you would call {@see ReloadableConfiguration::watch()}
 * instead (blocking loop, interruptible).
 *
 * Run from the package root:
 *   php examples/live.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dir = sys_get_temp_dir() . '/configure-it-live';
@mkdir($dir, 0o777, true);
$file = $dir . '/config.json';

file_put_contents($file, (string) json_encode([
    'feature' => ['enabled' => false],
    'rate' => 10,
], JSON_THROW_ON_ERROR));

$reporter = new ResolutionReporter();

$builder = (\ItPack\ConfigureIt\Wiring\Configurations::builder())
    ->addReader(new FileReader())
    ->addSource(new FileSource($dir))
    ->addLoader(new PhpLoader())
    ->addLoader(new JsonLoader());

$watcher = new PollingWatcher([$file], $reporter, 1000);

$config = new ReloadableConfiguration($builder, $watcher, $reporter);

echo 'Initial -> feature.enabled = ' . var_export($config->get('feature.enabled'), true) . PHP_EOL;

// A reader captured the v1 reference before the change.
$captured = $config->snapshot();

// Prime the watcher baseline so the change below is detected (watch() does this
// on its first iteration; here we drive it explicitly).
$watcher->tick();

// --- Simulate an external edit of the config file. ---
file_put_contents($file, (string) json_encode([
    'feature' => ['enabled' => true],
    'rate' => 50,
], JSON_THROW_ON_ERROR));

// In production this happens automatically inside watch(); here we drive one tick.
$watcher->tick();

echo 'After edit -> feature.enabled = ' . var_export($config->get('feature.enabled'), true) . PHP_EOL;
echo 'After edit -> rate            = ' . var_export($config->get('rate'), true) . PHP_EOL;
echo 'Captured v1 -> feature.enabled still = ' . var_export($captured->get('feature.enabled'), true) . PHP_EOL;

$types = array_map(static fn ($o) => $o->type(), $reporter->occurrences());
echo 'Occurrences: ' . implode(', ', $types) . PHP_EOL;

// --- Production wiring with kernel-level watching. ---
// $watcher = new FsWatcher([$file], $reporter);
// $config  = new ReloadableConfiguration($builder, $watcher, $reporter);
// $config->watch(); // blocks, reloading on every change until stop().
