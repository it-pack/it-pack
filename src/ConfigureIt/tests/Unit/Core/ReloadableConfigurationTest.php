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
use Tests\Support\Extension\VirtualFileSystem;
use Throwable;

class ReloadableConfigurationTest extends Unit
{
    use VirtualFileSystem;

    private string $dir;

    private string $file;

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

    public function testFailedReloadObservationNamesTheWatcherAndTheCause(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        file_put_contents($this->file, '{"broken"');

        $config->reload();

        $context = $reporter->observations()[0]->context();
        verify($context['watcher'])->equals(PollingWatcher::class);
        verify(is_string($context['error']))->true();
        verify($context['error'])->notEmpty();

        // No reload occurrence is recorded for a failed swap.
        $types = array_map(static fn ($o) => $o->type(), $reporter->occurrences());
        verify(in_array('ConfigurationReloaded', $types, true))->false();
    }

    public function testConstructorFailsFastOnABrokenInitialConfiguration(): void
    {
        file_put_contents($this->file, '{"feature":');

        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $this->expectException(Throwable::class);

        new ReloadableConfiguration($this->builder(), $watcher, $reporter);
    }

    public function testWorksWithoutAnInjectedReporter(): void
    {
        $watcher = new PollingWatcher([$this->file], new ResolutionReporter(), 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher);

        verify($config->bool('feature.enabled'))->false();

        $config->reload();

        verify($config->bool('feature.enabled'))->false();
    }

    /**
     * The facade is a thin, always-current delegate: every typed accessor must
     * read through to the snapshot held at call time.
     */
    public function testDelegatesEveryTypedAccessorToTheLiveSnapshot(): void
    {
        file_put_contents($this->file, (string) json_encode([
            'feature' => ['enabled' => true],
            'port' => 8080,
            'ratio' => 0.25,
            'name' => 'svc',
            'numbers' => [1, 2, 3],
            'tags' => ['alpha', 'beta'],
        ]));

        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);
        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        verify($config->has('port'))->true();
        verify($config->has('nope'))->false();
        verify($config->exists('feature.enabled'))->true();
        verify($config->exists('feature.missing'))->false();

        verify($config->int('port'))->equals(8080);
        verify($config->int('missing.port', 1234))->equals(1234);

        verify($config->float('ratio'))->equals(0.25);
        verify($config->float('missing.ratio', 1.5))->equals(1.5);

        verify($config->bool('feature.enabled'))->true();
        verify($config->bool('missing.flag', false))->false();

        verify($config->string('name'))->equals('svc');
        verify($config->string('missing.name', 'fallback'))->equals('fallback');

        verify($config->list('numbers'))->equals([1, 2, 3]);
        verify($config->list('missing.numbers', [7]))->equals([7]);

        verify($config->stringList('tags'))->equals(['alpha', 'beta']);
        verify($config->stringList('missing.tags', ['x']))->equals(['x']);

        verify($config->all()['name'])->equals('svc');
        verify(array_key_exists('feature', $config->all()))->true();
    }

    public function testTypedAccessorsFollowTheSnapshotAcrossAReload(): void
    {
        file_put_contents($this->file, (string) json_encode(['port' => 8080, 'name' => 'before']));

        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);
        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        verify($config->int('port'))->equals(8080);
        verify($config->string('name'))->equals('before');
        $captured = $config->snapshot();

        file_put_contents($this->file, (string) json_encode(['port' => 9090, 'name' => 'after']));
        $config->reload();

        verify($config->int('port'))->equals(9090);
        verify($config->string('name'))->equals('after');
        verify($config->all()['port'])->equals(9090);
        // Immutability of the previously handed-out reference (ADR-0003).
        verify($captured->int('port'))->equals(8080);
    }

    public function testWatchDelegatesToTheWatcherAndStopInterruptsIt(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 1000);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        // stop() reaches the underlying watcher, so the blocking watch() returns
        // instead of entering its poll loop.
        $config->stop();
        $config->watch();

        verify($config->get('feature.enabled'))->false();

        $types = array_map(static fn ($o) => $o->type(), $reporter->occurrences());
        verify(in_array('ConfigurationChanged', $types, true))->false();
        verify(in_array('ConfigurationReloaded', $types, true))->false();
    }

    public function testStaleArtifactOnDiskIsReplacedByAFreshResolution(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);
        verify($config->bool('feature.enabled'))->false();

        // Simulate configuration drifting on disk without any watcher tick: the
        // facade keeps serving the stale-but-valid snapshot until told to reload.
        file_put_contents($this->file, '{"feature":{"enabled":true}}');
        verify($config->bool('feature.enabled'))->false();

        $config->reload();
        verify($config->bool('feature.enabled'))->true();

        $reloads = array_filter(
            $reporter->occurrences(),
            static fn ($o): bool => 'ConfigurationReloaded' === $o->type(),
        );
        verify(count($reloads))->equals(1);
    }

    public function testRecoversOnceTheBrokenEditIsFixed(): void
    {
        $reporter = new ResolutionReporter();
        $watcher = new PollingWatcher([$this->file], $reporter, 10);

        $config = new ReloadableConfiguration($this->builder(), $watcher, $reporter);

        $watcher->tick(); // baseline

        file_put_contents($this->file, '{"feature":'); // broken
        $watcher->tick();
        verify($config->bool('feature.enabled'))->false();
        verify($reporter->observations())->notEmpty();

        file_put_contents($this->file, '{"feature":{"enabled":true}}'); // fixed
        $watcher->tick();

        verify($config->bool('feature.enabled'))->true();
    }

    protected function _before(): void
    {
        $this->dir = $this->vfsPath('/reload-' . uniqid());
        mkdir($this->dir, 0o700, true);
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
        return \ItPack\ConfigureIt\Wiring\Configurations::builder()
            ->addReader(new FileReader())
            ->addSource(new FileSource($this->dir))
            ->addLoader(new PhpLoader())
            ->addLoader(new JsonLoader())
        ;
    }
}
