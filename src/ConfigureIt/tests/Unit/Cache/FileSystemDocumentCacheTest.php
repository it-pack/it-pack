<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Cache\FileSystemDocumentCache;
use ItPack\ConfigureIt\Contract\RemoteDocumentCacheInterface;

/**
 * Coverage for the raw remote-document cache: TTL honouring, the stale
 * last-good fallback used by {@see \ItPack\ConfigureIt\Adapter\CachingReader},
 * corrupt-artifact tolerance, directory creation and cache-key sanitization.
 *
 * @covers \ItPack\ConfigureIt\Cache\FileSystemDocumentCache
 */
final class FileSystemDocumentCacheTest extends Unit
{
    private string $dir;

    protected function _before(): void
    {
        $this->dir = sys_get_temp_dir() . '/cfg-doc-cache-' . uniqid();
    }

    protected function _after(): void
    {
        $this->removeTree($this->dir);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);

        foreach ($entries === false ? [] : array_diff($entries, ['.', '..']) as $item) {
            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeTree($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * Directory listing by shell pattern. Deliberately scandir-based rather than
     * glob-based: sanitized cache keys may legitimately start with a dot (e.g.
     * from a `../` traversal attempt), which glob() hides by default.
     *
     * @return list<string>
     */
    private function files(string $pattern): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }

        $entries = scandir($this->dir);
        $matched = [];

        foreach ($entries === false ? [] : array_diff($entries, ['.', '..']) as $item) {
            if (fnmatch($pattern, $item)) {
                $matched[] = $this->dir . '/' . $item;
            }
        }

        return $matched;
    }

    // ---------------------------------------------------------------- wiring

    public function testImplementsTheRemoteDocumentCachePort(): void
    {
        self::assertInstanceOf(RemoteDocumentCacheInterface::class, new FileSystemDocumentCache($this->dir));
    }

    public function testMissingDirectoryIsCreatedRecursivelyAndNeverWorldAccessible(): void
    {
        $nested = $this->dir . '/nested/deep';

        verify(is_dir($nested))->false();

        new FileSystemDocumentCache($nested);

        verify(is_dir($nested))->true();
        // Cached documents may embed secrets: no bits for "other" regardless of umask.
        verify(fileperms($nested) & 0o007)->equals(0);
    }

    public function testExistingDirectoryIsReusedWithoutError(): void
    {
        mkdir($this->dir, 0o770, true);
        file_put_contents($this->dir . '/keep-me.txt', 'sentinel');

        new FileSystemDocumentCache($this->dir);
        new FileSystemDocumentCache($this->dir);

        verify(is_file($this->dir . '/keep-me.txt'))->true();
    }

    // ------------------------------------------------------------ round trip

    public function testSetAndGetRoundTripWithinTtl(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set('consul-app', '{"db":{"host":"localhost"}}', 60);

        verify($cache->get('consul-app'))->equals('{"db":{"host":"localhost"}}');
        verify($cache->getLastGood('consul-app'))->equals('{"db":{"host":"localhost"}}');
        verify(count($this->files('*.php')))->equals(1);
    }

    public function testOverwritingAnEntryReplacesTheDocument(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set('etcd', 'v1', 60);
        $cache->set('etcd', 'v2', 60);

        verify($cache->get('etcd'))->equals('v2');
        verify(count($this->files('*.php')))->equals(1);
    }

    public function testWritesAreAtomicWithoutTemporaryResidue(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set('atomic', 'payload', 60);

        verify($this->files('*.tmp*'))->equals([]);
    }

    public function testEmptyDocumentRoundTripsAndIsNotConfusedWithAMiss(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set('empty', '', 60);

        verify($cache->get('empty'))->equals('');
        verify($cache->getLastGood('empty'))->equals('');
    }

    public function testDocumentWithQuotesAndNewlinesSurvivesVarExportRoundTrip(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);
        $document = "line1\n'single' \"double\" \\backslash\n\$var";

        $cache->set('tricky', $document, 60);

        verify($cache->get('tricky'))->equals($document);
    }

    // ------------------------------------------------------------------ miss

    public function testUnknownKeyIsAMissOnBothReadPaths(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        verify($cache->get('never-written'))->null();
        verify($cache->getLastGood('never-written'))->null();
    }

    // ------------------------------------------------------------------- TTL

    public function testExpiredEntryIsAMissButStaysAvailableAsLastGood(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set('stale', 'stale-but-usable', -10);

        verify($cache->get('stale'))->null();
        // Resilience contract: CachingReader serves the stale copy on fetch failure.
        verify($cache->getLastGood('stale'))->equals('stale-but-usable');
    }

    public function testEntryExpiringInTheFutureIsStillFresh(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set('fresh', 'fresh-doc', 3600);

        verify($cache->get('fresh'))->equals('fresh-doc');
    }

    public function testNonIntegerExpiryMetadataIsTreatedAsNeverExpiring(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);
        $this->writeArtifact('typed', "array('expires' => 'not-a-timestamp', 'document' => 'served')");

        verify($cache->get('typed'))->equals('served');
        verify($cache->getLastGood('typed'))->equals('served');
    }

    public function testAbsentExpiryMetadataIsNotFreshButRemainsLastGood(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);
        // A "never expires" write stores expires => null, which is not a typed
        // freshness assertion; only the last-good path serves it.
        $cache->set('no-ttl', 'immortal');

        verify($cache->get('no-ttl'))->null();
        verify($cache->getLastGood('no-ttl'))->equals('immortal');
    }

    // -------------------------------------------------------------- corrupt

    /**
     * @dataProvider corruptArtifacts
     */
    public function testCorruptArtifactFallsBackToAMissInsteadOfBlowingUp(string $exported): void
    {
        $cache = new FileSystemDocumentCache($this->dir);
        $this->writeArtifact('corrupt', $exported);

        verify($cache->get('corrupt'))->null();
        verify($cache->getLastGood('corrupt'))->null();
    }

    /**
     * @return array<string, array{string}>
     */
    public function corruptArtifacts(): array
    {
        return [
            'not an array'            => ["'just-a-string'"],
            'null payload'            => ['null'],
            'missing document key'    => ["array('expires' => null)"],
            'null document'           => ["array('expires' => 1, 'document' => NULL)"],
            'non-string document'     => ["array('expires' => 1, 'document' => 42)"],
            'array document'          => ["array('expires' => 1, 'document' => array('a'))"],
        ];
    }

    public function testArtifactWithoutReturnStatementIsAMiss(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);
        file_put_contents($this->dir . '/silent.php', '<?php $noop = 1;');

        verify($cache->get('silent'))->null();
        verify($cache->getLastGood('silent'))->null();
    }

    public function testDocumentPresentButExpiryMissingIsRecoveredByLastGoodOnly(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);
        $this->writeArtifact('half', "array('document' => 'recovered')");

        verify($cache->get('half'))->null();
        verify($cache->getLastGood('half'))->equals('recovered');
    }

    // ------------------------------------------------------- sanitization

    /**
     * @dataProvider hostileKeys
     */
    public function testHostileKeysAreSanitizedIntoTheCacheDirectory(string $key): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set($key, 'contained', 60);

        $written = $this->files('*.php');
        verify(count($written))->equals(1);

        // The artifact never escapes the cache directory, whatever the key holds.
        verify(realpath(dirname($written[0])))->equals(realpath($this->dir));
        verify(basename($written[0]))->equals(
            preg_replace('/[^A-Za-z0-9_.-]/', '_', $key) . '.php',
        );

        // …and the sanitized key is still readable back through the same key.
        verify($cache->get($key))->equals('contained');
    }

    /**
     * @return array<string, array{string}>
     */
    public function hostileKeys(): array
    {
        return [
            'traversal'       => ['../../etc/passwd'],
            'nested path'     => ['sub/dir/key'],
            'reader key'      => ['JSON:https://example.com/config?v=1'],
            'shell-ish'       => ['a;rm -rf /'],
            'null byte-ish'   => ['a b c'],
            'windows path'    => ['C:\\config\\app'],
        ];
    }

    public function testKeysCollapsingToTheSameSafeNameShareOneArtifact(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);

        $cache->set('a/b', 'first', 60);
        $cache->set('a:b', 'second', 60);

        verify(count($this->files('*.php')))->equals(1);
        verify($cache->get('a/b'))->equals('second');
    }

    public function testTrailingSeparatorInTheDirectoryDoesNotDoubleUp(): void
    {
        mkdir($this->dir, 0o770, true);
        $cache = new FileSystemDocumentCache($this->dir . '/');

        $cache->set('slash', 'ok', 60);

        verify($cache->get('slash'))->equals('ok');
        verify($this->files('slash.php'))->notEmpty();
    }

    // ------------------------------------------------------- write failure

    public function testUnwritableTargetIsBestEffortAndLeavesNoResidue(): void
    {
        $cache = new FileSystemDocumentCache($this->dir);
        // A directory occupying the artifact path makes the atomic rename fail.
        mkdir($this->dir . '/blocked.php');

        $cache->set('blocked', 'never-stored', 60);

        verify($this->files('*.tmp*'))->equals([]);
        verify($cache->get('blocked'))->null();
        verify($cache->getLastGood('blocked'))->null();
    }

    private function writeArtifact(string $key, string $exported): void
    {
        file_put_contents($this->dir . '/' . $key . '.php', '<?php return ' . $exported . ';');
    }
}
