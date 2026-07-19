<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\Key;
use ItPack\ConfigureIt\Exception\ConfigurationException;

class KeyTest extends Unit
{
    public function testFromStringParsesDottedPath(): void
    {
        $key = Key::fromString('database.host');

        verify($key->toString())->equals('database.host');
    }

    public function testChildAppendsSegment(): void
    {
        $key = Key::fromString('database')->child('host');

        verify($key->toString())->equals('database.host');
        verify($key->child('port')->toString())->equals('database.host.port');
    }

    public function testChildDoesNotMutateOriginal(): void
    {
        $base = Key::fromString('app');
        $base->child('debug');

        verify($base->toString())->equals('app');
    }

    public function testGetReadsNestedValue(): void
    {
        $tree = ['database' => ['host' => 'localhost', 'port' => 5432]];

        verify(Key::fromString('database.host')->get($tree))->equals('localhost');
        verify(Key::fromString('database.port')->get($tree))->equals(5432);
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $tree = ['database' => ['host' => 'localhost']];

        verify(Key::fromString('database.missing')->get($tree, 'fallback'))->equals('fallback');
        verify(Key::fromString('database.missing')->get($tree))->null();
    }

    public function testHasReflectsPresence(): void
    {
        $tree = ['database' => ['host' => 'localhost']];

        verify(Key::fromString('database.host')->has($tree))->true();
        verify(Key::fromString('database.missing')->has($tree))->false();
    }

    public function testSetWritesNestedValueCreatingBranches(): void
    {
        $tree = [];

        Key::fromString('cache.pools.redis')->set($tree, 'tcp://localhost');

        verify($tree)->equals(['cache' => ['pools' => ['redis' => 'tcp://localhost']]]);
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        Key::fromString('');
    }

    public function testKeyWithEmptySegmentIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        Key::fromString('app..debug');
    }

    public function testChildWithEmptySegmentIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);

        Key::fromString('app')->child('');
    }

    public function testIsAssociativeDistinguishesBranchesFromLists(): void
    {
        verify(Key::isAssociative([]))->false();
        verify(Key::isAssociative(['a' => 1]))->true();
        verify(Key::isAssociative([1, 2, 3]))->false();
    }
}
