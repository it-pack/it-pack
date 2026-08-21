<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\FileReader;
use ItPack\ConfigureIt\Adapter\FileSource;
use ItPack\ConfigureIt\Adapter\JsonLoader;
use ItPack\ConfigureIt\Adapter\PhpLoader;
use ItPack\ConfigureIt\Adapter\PollingWatcher;
use ItPack\ConfigureIt\Core\ConfigurationBuilder;
use ItPack\ConfigureIt\Core\ReloadableConfiguration;
use ItPack\ConfigureIt\Execution\ResolutionReporter;

class ReloadableConfigurationTest extends Unit
{
    private string $dir;

    private string $file;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/reload-' . uniqid();
        mkdir($this->dir, 0o777, true);
        $this->file = $this->dir . '/config.json';
        file_put_contents($this->file, '{"feature":{"enabled":false}}');
    }

    protected function _after(): void
    {
        foreach (array_diff(scandir($this->dir), ['.', '..']) as $item) {
            unlink($this->dir . '/' . $item);
        }

        rmdir($this->dir);
    }

    private function builder(): ConfigurationBuilder
    {
        return new ConfigurationBuilder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
        ;
    }

    public function testResolvesInitialSnapshot(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        verify($config->get('feature.enabled'))->false();
    }

    public function testReloadSwapsSnapshotWhileOldReferenceStaysValid(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        $captured = $config->snapshot(); // a reader holding the v1 reference

        file_put_contents($this->file, '{"feature":{"enabled":true}}');

        $config->reload();

        // New facade reads see the new value…
        verify($config->get('feature.enabled'))->true();
        // …but the previously captured immutable snapshot is untouched.
        verify($captured->get('feature.enabled'))->false();

        $types = array_map(static fn ($o) => $o->type(), $reporter->occurrences());
        verify(in_array('ConfigurationReloaded', $types, true))->true();
    }

    public function testReactsToWatcherChangeEvents(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        $watcher->tick(); // establish baseline, no dispatch

        file_put_contents($this->file, '{"feature":{"enabled":true}}');

        $watcher->tick(); // change detected -> facade reloads

        verify($config->get('feature.enabled'))->true();
    }

    public function testFailedReloadKeepsLastGoodAndObserves(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        $watcher->tick(); // baseline

        file_put_contents($this->file, '{"feature":'); // invalid JSON

        $watcher->tick(); // reload fails

        // Last-good snapshot is still served.
        verify($config->get('feature.enabled'))->false();

        $observations = $reporter->observations();
        verify($observations)->notEmpty();
        verify($observations[0]->level())->equals('error');
    }
}
