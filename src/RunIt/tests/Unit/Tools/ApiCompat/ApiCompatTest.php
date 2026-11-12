<?php

declare(strict_types=1);

namespace Tests\Unit\Tools\ApiCompat;

use Codeception\Test\Unit;
use ReflectionMethod;
use Tools\ApiCompat\ApiCompat;

final class ApiCompatTest extends Unit
{
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

    public function testRetainedAndAddedInterfacesAreNotReportedAsRemoved(): void
    {
        $baseline = ['Example' => $this->entry('class', ['OldInterface' => 'OldInterface'])];
        $current = ['Example' => $this->entry('class', ['NewInterface', 'OldInterface'])];

        self::assertSame([], $this->compare($current, $baseline));
    }

    /**
     * @param array<array-key, string> $interfaces
     * @return array<string, mixed>
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
     */
    private function compare(array $current, array $baseline): array
    {
        $method = new ReflectionMethod(ApiCompat::class, 'compareApi');

        $result = $method->invoke(new ApiCompat(['api-compat.php']), $current, $baseline);
        self::assertIsArray($result);

        return $result;
    }
}
