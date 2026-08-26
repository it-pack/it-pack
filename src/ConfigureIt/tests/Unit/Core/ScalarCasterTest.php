<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\ScalarCaster;
use ItPack\ConfigureIt\Exception\TypeException;

/**
 * The coercion rules shared by typed snapshot accessors and freeze-time
 * validation. Table-driven over the full accept/reject matrix so the leniency
 * for environment-sourced values (numeric strings, truthy strings) stays exactly
 * as wide as intended — and no wider.
 *
 * @covers \ItPack\ConfigureIt\Core\ScalarCaster
 */
final class ScalarCasterTest extends Unit
{
    private const string PATH = 'cfg.key';

    private function cast(string $method, mixed $value): mixed
    {
        return match ($method) {
            'toInt'    => ScalarCaster::toInt($value, self::PATH),
            'toFloat'  => ScalarCaster::toFloat($value, self::PATH),
            'toBool'   => ScalarCaster::toBool($value, self::PATH),
            'toString' => ScalarCaster::toString($value, self::PATH),
            'toList'   => ScalarCaster::toList($value, self::PATH),
            'toMap'    => ScalarCaster::toMap($value, self::PATH),
            default    => throw new \LogicException(sprintf('Unknown caster "%s" in test table.', $method)),
        };
    }

    /**
     * @dataProvider acceptedCoercions
     */
    public function testCoercesCompatibleValues(string $method, mixed $input, mixed $expected, string $expectedType): void
    {
        $actual = $this->cast($method, $input);

        verify($actual)->equals($expected);
        verify(get_debug_type($actual))->equals($expectedType);
    }

    /**
     * @return array<string, array{string, mixed, mixed, string}>
     */
    public function acceptedCoercions(): array
    {
        return [
            // --- int ---------------------------------------------------------
            'int from int'              => ['toInt', 5, 5, 'int'],
            'int from negative int'     => ['toInt', -5, -5, 'int'],
            'int from zero'             => ['toInt', 0, 0, 'int'],
            'int truncates float'       => ['toInt', 5.9, 5, 'int'],
            'int truncates neg float'   => ['toInt', -5.9, -5, 'int'],
            'int from float zero'       => ['toInt', 0.0, 0, 'int'],
            'int from numeric string'   => ['toInt', '42', 42, 'int'],
            'int from signed string'    => ['toInt', '-42', -42, 'int'],
            'int from zero string'      => ['toInt', '0', 0, 'int'],
            'int from padded string'    => ['toInt', '007', 7, 'int'],
            'int from exponent string'  => ['toInt', '1e3', 1000, 'int'],
            'int from spaced string'    => ['toInt', ' 42', 42, 'int'],

            // --- float -------------------------------------------------------
            'float from int'            => ['toFloat', 1, 1.0, 'float'],
            'float from zero'           => ['toFloat', 0, 0.0, 'float'],
            'float from float'          => ['toFloat', 1.5, 1.5, 'float'],
            'float from float string'   => ['toFloat', '1.5', 1.5, 'float'],
            'float from int string'     => ['toFloat', '2', 2.0, 'float'],
            'float from signed string'  => ['toFloat', '-0.25', -0.25, 'float'],
            'float from exponent'       => ['toFloat', '1e3', 1000.0, 'float'],

            // --- bool --------------------------------------------------------
            'bool from true'            => ['toBool', true, true, 'bool'],
            'bool from false'           => ['toBool', false, false, 'bool'],
            'bool from int one'         => ['toBool', 1, true, 'bool'],
            'bool from int zero'        => ['toBool', 0, false, 'bool'],
            'bool from "true"'          => ['toBool', 'true', true, 'bool'],
            'bool from "TRUE"'          => ['toBool', 'TRUE', true, 'bool'],
            'bool from padded "True"'   => ['toBool', '  True  ', true, 'bool'],
            'bool from "1"'             => ['toBool', '1', true, 'bool'],
            'bool from "on"'            => ['toBool', 'on', true, 'bool'],
            'bool from "ON"'            => ['toBool', 'ON', true, 'bool'],
            'bool from "yes"'           => ['toBool', 'yes', true, 'bool'],
            'bool from "y"'             => ['toBool', 'y', true, 'bool'],
            'bool from "false"'         => ['toBool', 'false', false, 'bool'],
            'bool from "FALSE"'         => ['toBool', 'FALSE', false, 'bool'],
            'bool from "0"'             => ['toBool', '0', false, 'bool'],
            'bool from "off"'           => ['toBool', 'off', false, 'bool'],
            'bool from "no"'            => ['toBool', 'no', false, 'bool'],
            'bool from "n"'             => ['toBool', 'n', false, 'bool'],

            // --- string ------------------------------------------------------
            'string from string'        => ['toString', 'x', 'x', 'string'],
            'string from empty string'  => ['toString', '', '', 'string'],
            'string from int'           => ['toString', 5, '5', 'string'],
            'string from negative int'  => ['toString', -5, '-5', 'string'],
            'string from float'         => ['toString', 1.5, '1.5', 'string'],
            'string from whole float'   => ['toString', 0.0, '0', 'string'],
            'string from true'          => ['toString', true, '1', 'string'],
            'string from false'         => ['toString', false, '', 'string'],

            // --- list --------------------------------------------------------
            'list from empty array'     => ['toList', [], [], 'array'],
            'list from int list'        => ['toList', [1, 2], [1, 2], 'array'],
            'list from string list'     => ['toList', ['a', 'b'], ['a', 'b'], 'array'],
            'list from nested list'     => ['toList', [[1], [2]], [[1], [2]], 'array'],

            // --- map ---------------------------------------------------------
            'map from string keys'      => ['toMap', ['a' => 1], ['a' => 1], 'array'],
            'map from offset int keys'  => ['toMap', [1 => 'x'], [1 => 'x'], 'array'],
            'map from sparse int keys'  => ['toMap', [0 => 'a', 2 => 'b'], [0 => 'a', 2 => 'b'], 'array'],
        ];
    }

    /**
     * @dataProvider rejectedCoercions
     */
    public function testRejectsIncompatibleValues(string $method, mixed $input, string $expectedMessage): void
    {
        try {
            $this->cast($method, $input);
            self::fail(sprintf('Expected %s() to reject the value.', $method));
        } catch (TypeException $e) {
            verify($e->getMessage())->equals($expectedMessage);
        }
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public function rejectedCoercions(): array
    {
        $mismatch = static fn (string $expected, string $actual): string => sprintf(
            'Configuration key "cfg.key" expected %s, got %s.',
            $expected,
            $actual,
        );

        return [
            // A fractional string is *not* silently truncated to int.
            'int rejects float string'  => ['toInt', '4.2', $mismatch('int', 'string')],
            'int rejects word'          => ['toInt', 'abc', $mismatch('int', 'string')],
            'int rejects empty string'  => ['toInt', '', $mismatch('int', 'string')],
            'int rejects true'          => ['toInt', true, $mismatch('int', 'bool')],
            'int rejects false'         => ['toInt', false, $mismatch('int', 'bool')],
            'int rejects null'          => ['toInt', null, $mismatch('int', 'null')],
            'int rejects array'         => ['toInt', [], $mismatch('int', 'array')],
            'int rejects object'        => ['toInt', new \stdClass(), $mismatch('int', 'stdClass')],

            'float rejects word'        => ['toFloat', 'abc', $mismatch('float', 'string')],
            'float rejects empty'       => ['toFloat', '', $mismatch('float', 'string')],
            'float rejects true'        => ['toFloat', true, $mismatch('float', 'bool')],
            'float rejects null'        => ['toFloat', null, $mismatch('float', 'null')],
            'float rejects array'       => ['toFloat', [1], $mismatch('float', 'array')],
            'float rejects object'      => ['toFloat', new \stdClass(), $mismatch('float', 'stdClass')],

            // Only the exact ints 1/0 are truthy; other numbers are ambiguous.
            'bool rejects int two'      => ['toBool', 2, $mismatch('bool', 'int')],
            'bool rejects negative int' => ['toBool', -1, $mismatch('bool', 'int')],
            'bool rejects float one'    => ['toBool', 1.0, $mismatch('bool', 'float')],
            'bool rejects word'         => ['toBool', 'maybe', $mismatch('bool', 'string')],
            'bool rejects empty string' => ['toBool', '', $mismatch('bool', 'string')],
            'bool rejects "2"'          => ['toBool', '2', $mismatch('bool', 'string')],
            'bool rejects null'         => ['toBool', null, $mismatch('bool', 'null')],
            'bool rejects array'        => ['toBool', [], $mismatch('bool', 'array')],
            'bool rejects object'       => ['toBool', new \stdClass(), $mismatch('bool', 'stdClass')],

            'string rejects null'       => ['toString', null, $mismatch('string', 'null')],
            'string rejects array'      => ['toString', ['a'], $mismatch('string', 'array')],
            'string rejects object'     => ['toString', new \stdClass(), $mismatch('string', 'stdClass')],

            'list rejects map'          => ['toList', ['a' => 1], 'Configuration key "cfg.key" expected a list, got array.'],
            'list rejects string'       => ['toList', 'x', 'Configuration key "cfg.key" expected a list, got string.'],
            'list rejects null'         => ['toList', null, 'Configuration key "cfg.key" expected a list, got null.'],
            'list rejects int'          => ['toList', 5, 'Configuration key "cfg.key" expected a list, got int.'],

            // An empty array is a list, therefore never a map.
            'map rejects empty array'   => ['toMap', [], $mismatch('map', 'array')],
            'map rejects list'          => ['toMap', [1, 2], $mismatch('map', 'array')],
            'map rejects string'        => ['toMap', 'x', $mismatch('map', 'string')],
            'map rejects null'          => ['toMap', null, $mismatch('map', 'null')],
        ];
    }

    public function testFailuresCarryTheOffendingPath(): void
    {
        try {
            ScalarCaster::toInt('nope', 'database.connections.default.port');
            self::fail('Expected the cast to fail.');
        } catch (TypeException $e) {
            verify(str_contains($e->getMessage(), 'database.connections.default.port'))->true();
        }
    }
}
