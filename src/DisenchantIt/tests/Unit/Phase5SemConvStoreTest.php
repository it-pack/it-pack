<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\SemConv\FileSemConvStore;
use ItPack\DisenchantIt\Adapter\SemConv\SemConvCollector;
use ItPack\DisenchantIt\Exception\SemConvCollisionException;
use ItPack\DisenchantIt\ValueObject\Rewrite\ConstantPlacement;
use ItPack\DisenchantIt\ValueObject\SemConv\SemConvConfig;

final class Phase5SemConvStoreTest extends Unit
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/disenchant_semconv_' . uniqid('', true) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    private function placement(string $name, string $value, string $type = 'string'): ConstantPlacement
    {
        return new ConstantPlacement(
            className: 'C',
            constantName: $name,
            value: $value,
            type: $type,
            occurrences: [],
        );
    }

    public function testStoreAccumulatesAcrossRuns(): void
    {
        $store = new FileSemConvStore($this->tmpFile);
        $collector = new SemConvCollector(new SemConvConfig(), $store);

        // First run collects ADMIN_USER and persists to the store.
        $first = $collector->collect([$this->placement('ADMIN_USER', 'admin-user')]);
        $this->assertStringContainsString("public const string ADMIN_USER = 'admin-user';", $first);
        $this->assertFileExists($this->tmpFile);

        // Second run loads the prior registry from the store (existingSource is
        // null) and merges a new entry without discarding the old one.
        $second = $collector->collect([$this->placement('STATUS_ACTIVE', 'active')], null);
        $this->assertStringContainsString('ADMIN_USER', $second);
        $this->assertStringContainsString('STATUS_ACTIVE', $second);

        // A third run that re-collects ADMIN_USER finds it already present and
        // merges idempotently (no duplicate, no collision).
        $third = $collector->collect([$this->placement('ADMIN_USER', 'admin-user')], null);
        $this->assertSame(
            substr_count($third, 'public const string ADMIN_USER'),
            1,
            'ADMIN_USER appears exactly once after re-collection',
        );
    }

    public function testMergePreservesExistingEntries(): void
    {
        $store = new FileSemConvStore($this->tmpFile);
        $collector = new SemConvCollector(new SemConvConfig(), $store);

        $collector->collect([$this->placement('ADMIN_USER', 'admin-user')]);
        $collector->collect([$this->placement('STATUS_ACTIVE', 'active')], null);

        $contents = $store->load();
        $this->assertStringContainsString('ADMIN_USER', $contents);
        $this->assertStringContainsString('STATUS_ACTIVE', $contents);
    }

    public function testCollisionWithDifferingValueIsHardError(): void
    {
        $store = new FileSemConvStore($this->tmpFile);
        $collector = new SemConvCollector(new SemConvConfig(), $store);

        $collector->collect([$this->placement('CODE', 'one')]);
        $this->expectException(SemConvCollisionException::class);

        // Same name, differing value → hard error (per domain invariant).
        $collector->collect([$this->placement('CODE', 'two')], null);
    }

    public function testCollectorWithoutStoreStaysPure(): void
    {
        $collector = new SemConvCollector(new SemConvConfig());
        $out = $collector->collect([$this->placement('ADMIN_USER', 'admin-user')]);
        $this->assertStringContainsString('ADMIN_USER', $out);
        $this->assertFileDoesNotExist($this->tmpFile);
    }
}
