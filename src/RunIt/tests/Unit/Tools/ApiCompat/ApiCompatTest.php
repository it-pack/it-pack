<?php

declare(strict_types=1);

namespace Tests\Unit\Tools\ApiCompat;

use Codeception\Test\Unit;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionException;
use ReflectionMethod;
use Tools\ApiCompat\ApiCompat;

#[CoversClass(ApiCompat::class)]
final class ApiCompatTest extends Unit
{
    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    public function testRemovedImplementedAndExtendedInterfacesAreBreaks(): void
    {
        $class = $this->entry('class', ['OldInterface' => 'OldInterface']);
        $interface = $this->entry('interface', ['ParentInterface' => 'ParentInterface']);
        $baseline = ['ExampleClass' => $class, 'ExampleInterface' => $interface];
        $current = [
            'ExampleClass' => $this->entry('class', []),
            'ExampleInterface' => $this->entry('interface', []),
        ];

        $issues = $this->compare($current, $baseline);

        self::assertSame([
            ['message' => 'Removed implemented or extended interface OldInterface from ExampleClass',
                'symbol' => 'ExampleClass', 'severity' => 'break'],
            ['message' => 'Removed implemented or extended interface ParentInterface from ExampleInterface',
                'symbol' => 'ExampleInterface', 'severity' => 'break'],
        ], $issues);
    }

    /**
     * @throws ReflectionException
     * @throws JsonException
     */
    public function testRetainedAndAddedInterfacesAreNotReportedAsRemoved(): void
    {
        $baseline = ['Example' => $this->entry('class', ['OldInterface' => 'OldInterface'])];
        $current = ['Example' => $this->entry('class', ['NewInterface', 'OldInterface'])];

        self::assertSame([], $this->compare($current, $baseline));
    }

    public function testRecordedBuiltInMethodSignatureChangesRemainBreaks(): void
    {
        $baseline = ['Example' => $this->entry('interface', ['Countable'])];
        $baseline['Example']['methods']['count'] = [
            'static' => false,
            'return' => 'int',
            'params' => [],
        ];
        $current = $baseline;
        $current['Example']['methods']['count']['return'] = 'string';
        $current['Example']['methods']['count']['internalInterface'] = 'Countable';

        self::assertContains([
            'message' => 'Return type narrowed on Example::count(): int -> string',
            'symbol' => 'Example',
            'severity' => 'break',
        ], $this->compare($current, $baseline));

        unset($current['Example']['methods']['count']);
        self::assertContains([
            'message' => 'Removed method Example::count()',
            'symbol' => 'Example',
            'severity' => 'break',
        ], $this->compare($current, $baseline));
    }

    public function testUserlandMethodAdditionIsNotHiddenByExistingBuiltInParent(): void
    {
        $baseline = ['Example' => $this->entry('interface', ['Countable'])];
        $current = $baseline;
        $current['Example']['methods']['count'] = ['static' => false, 'return' => 'int', 'params' => []];

        self::assertContains([
            'message' => 'Added method to interface Example::count() (breaks third-party implementers)',
            'symbol' => 'Example',
            'severity' => 'break',
        ], $this->compare($current, $baseline));
    }

    /** @param list<string> $publicDirs */
    private function checker(array $publicDirs = []): ApiCompat
    {
        return new ApiCompat(['api-compat.php'], [
            'publicDirs' => $publicDirs,
            'internalNamespaces' => [],
            'baseline' => '',
            'schema' => ['path' => '', 'baseline' => ''],
        ]);
    }

    /**
     * @param array<array-key, string> $interfaces
     * @return array{
     *     kind: string, extends: null, implements: array<array-key, string>, constants: array<mixed>,
     *     methods: array<string, array<string, mixed>>, cases: list<string>
     * }
     */
    private function entry(string $kind, array $interfaces): array
    {
        return [
            'kind' => $kind,
            'extends' => null,
            'implements' => $interfaces,
            'constants' => [],
            'methods' => [],
            'cases' => [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $current
     * @param array<string, array<string, mixed>> $baseline
     * @return array<array-key, mixed>
     * @throws ReflectionException|JsonException
     */
    private function compare(array $current, array $baseline): array
    {
        $method = new ReflectionMethod(ApiCompat::class, 'compareApi');

        $result = $method->invoke($this->checker(), $current, $baseline);
        self::assertIsArray($result);

        return $result;
    }
}
