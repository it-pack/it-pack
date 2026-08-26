<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\RedactingRenderer;
use stdClass;

/**
 * The single rendering seam for configuration values (M4 — Epic E5.2). Covers
 * the mask contract for every value shape a secret can take, plus the
 * pass-through formatting of ordinary values.
 *
 * @covers \ItPack\ConfigureIt\Core\RedactingRenderer
 */
final class RedactingRendererTest extends Unit
{
    public function testDefaultMaskIsExposed(): void
    {
        verify(new RedactingRenderer()->mask())->equals('****');
    }

    public function testMaskIsConfigurable(): void
    {
        $renderer = new RedactingRenderer('[redacted]');

        verify($renderer->mask())->equals('[redacted]');
        verify($renderer->render('s3cr3t', true))->equals('[redacted]');
    }

    /**
     * A secret must never leak, whatever its runtime shape.
     *
     * @dataProvider secretShapes
     */
    public function testSensitiveValuesAlwaysRenderAsTheMask(mixed $value): void
    {
        $renderer = new RedactingRenderer();

        verify($renderer->render($value, true))->equals('****');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public function secretShapes(): array
    {
        return [
            'string'       => ['s3cr3t-value'],
            'empty string' => [''],
            'int'          => [1234],
            'float'        => [1.5],
            'true'         => [true],
            'false'        => [false],
            'null'         => [null],
            'list'         => [['a', 'b']],
            'map'          => [['user' => 'root', 'pass' => 'hunter2']],
            'object'       => [new stdClass()],
        ];
    }

    /**
     * @dataProvider clearValues
     */
    public function testFormatsNonSensitiveValues(mixed $value, string $expected): void
    {
        verify(new RedactingRenderer()->render($value, false))->equals($expected);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public function clearValues(): array
    {
        return [
            'map becomes json'    => [['a' => 1], '{"a":1}'],
            'list becomes json'   => [[1, 2], '[1,2]'],
            'empty array'         => [[], '[]'],
            'true'                => [true, 'true'],
            'false'               => [false, 'false'],
            'null'                => [null, 'null'],
            'int'                 => [8080, '8080'],
            'negative int'        => [-1, '-1'],
            'float'               => [0.5, '0.5'],
            'whole float'         => [2.0, '2'],
            'string'              => ['api', 'api'],
            'empty string'        => ['', ''],
            'numeric string'      => ['0', '0'],
        ];
    }

    public function testNonScalarValuesFallBackToJson(): void
    {
        $object = new stdClass();
        $object->host = 'localhost';
        $object->port = 5432;

        verify(new RedactingRenderer()->render($object, false))->equals('{"host":"localhost","port":5432}');
        verify(new RedactingRenderer()->render(new stdClass(), false))->equals('{}');
    }

    public function testBooleansAreNeverRenderedAsPhpEmptyString(): void
    {
        // A naive (string) cast would render false as '', which is
        // indistinguishable from an empty configuration value.
        $renderer = new RedactingRenderer();

        verify($renderer->render(false, false))->notEquals($renderer->render('', false));
    }
}
