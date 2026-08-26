<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ConfigurationSnapshot;
use ItPack\ConfigureIt\Exception\TypeException;

class ConfigurationSnapshotTypedTest extends Unit
{
    public function testIntCoercesNumericStringAndFloat(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->int('app.port'))->equals(8080);
        verify($snapshot->int('app.whole'))->equals(9);
    }

    public function testFloatCoercesNumericString(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->float('app.ratio'))->equals(3.5);
        verify($snapshot->float('app.port'))->equals(8080.0);
        verify($snapshot->float('app.whole'))->equals(9.0);
    }

    public function testBoolAcceptsTruthyStrings(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->bool('app.debug'))->true();
        verify($snapshot->bool('app.flag'))->true();
    }

    public function testStringReturnsStringAndScalarCast(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->string('app.name'))->equals('service');
        verify($snapshot->string('app.port'))->equals('8080');
    }

    public function testListReturnsSequentialArray(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->list('app.tags'))->equals(['a', 'b']);
    }

    public function testStringListCastsEachElement(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->stringList('app.tags'))->equals(['a', 'b']);
    }

    public function testDefaultsUsedWhenKeyAbsent(): void
    {
        $snapshot = $this->snapshot();

        verify($snapshot->int('missing', 42))->equals(42);
        verify($snapshot->string('missing', 'x'))->equals('x');
        verify($snapshot->list('missing', []))->equals([]);
    }

    public function testMissingRequiredKeyThrows(): void
    {
        $snapshot = $this->snapshot();

        $this->expectException(TypeException::class);
        $this->expectExceptionMessage('Required configuration key "missing" is missing.');

        $snapshot->int('missing');
    }

    public function testTypeMismatchThrows(): void
    {
        $snapshot = $this->snapshot();

        $this->expectException(TypeException::class);
        $this->expectExceptionMessage('Configuration key "app.name" expected int, got string.');

        $snapshot->int('app.name');
    }

    public function testNonListThrowsOnListAccessor(): void
    {
        $snapshot = $this->snapshot();

        $this->expectException(TypeException::class);
        $this->expectExceptionMessage('Configuration key "db.host" expected a list, got string.');

        $snapshot->list('db.host');
    }
    private function snapshot(): ConfigurationSnapshot
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('app.debug', true, 'base', 10),
            new ConfigurationNode('app.port', '8080', 'base', 10),
            new ConfigurationNode('app.ratio', '3.5', 'base', 10),
            new ConfigurationNode('app.whole', 9.0, 'base', 10),
            new ConfigurationNode('app.name', 'service', 'base', 10),
            new ConfigurationNode('app.tags', ['a', 'b'], 'base', 10),
            new ConfigurationNode('app.flag', 'yes', 'base', 10),
            new ConfigurationNode('db.host', 'localhost', 'base', 10),
        ]);

        return ConfigurationSnapshot::fromGraph($graph);
    }
}
