<?php

declare(strict_types=1);

namespace Tests\Integration\Tools\ApiCompat;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\SequencedInterface;
use ReflectionMethod;
use Tools\ApiCompat\ApiCompat;

final class ApiCompatDiscoveryTest extends Unit
{
    public function testDiscoveryIncludesInheritedBuiltInInterfaceMethods(): void
    {
        $checker = new ApiCompat(['api-compat.php'], [
            'publicDirs' => ['src/Contract/Domain', 'src/Common/Contract'],
            'internalNamespaces' => [], 'baseline' => '', 'schema' => ['path' => '', 'baseline' => ''],
        ]);
        $map = new ReflectionMethod(ApiCompat::class, 'buildApiMap')->invoke($checker);
        $name = SequencedInterface::class;
        self::assertIsArray($map);
        self::assertArrayHasKey($name, $map);
        $entry = $map[$name];
        self::assertIsArray($entry);
        self::assertArrayHasKey('methods', $entry);
        self::assertIsArray($entry['methods']);
        self::assertArrayHasKey('count', $entry['methods']);
        $baseline = [$name => $entry];
        unset($baseline[$name]['methods']['count']);
        $compare = new ReflectionMethod(ApiCompat::class, 'compareApi');
        self::assertSame([], $compare->invoke($checker, [$name => $entry], $baseline));
        $baseline[$name]['implements'] = [];
        $issues = $compare->invoke($checker, [$name => $entry], $baseline);
        self::assertIsArray($issues);
        self::assertContains([
            'message' => "Added method to interface {$name}::count() (breaks third-party implementers)",
            'symbol' => $name, 'severity' => 'break',
        ], $issues);
    }
}
