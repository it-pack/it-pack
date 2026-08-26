<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Core\CastingProcessor;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Exception\CastException;
use ItPack\ConfigureIt\ValueObject\ProcessorStage;

class CastingProcessorTest extends Unit
{
    public function testCastsStringToTypedScalars(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10),
            new ConfigurationNode('ratio', '0.5', 'ini', 10),
            new ConfigurationNode('debug', 'true', 'ini', 10),
            new ConfigurationNode('name', 'api', 'ini', 10),
        ]);

        $next = new CastingProcessor([
            'port' => 'int',
            'ratio' => 'float',
            'debug' => 'bool',
            'name' => 'string',
        ])->process($graph);

        verify($next->node('port')->value())->equals(8080);
        verify($next->node('ratio')->value())->equals(0.5);
        verify($next->node('debug')->value())->equals(true);
        verify($next->node('name')->value())->equals('api');
    }

    public function testPreservesSourcePriorityAndMetadata(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10, ['origin' => 'ini']),
        ]);

        $next = new CastingProcessor(['port' => 'int'])->process($graph);

        $node = $next->node('port');
        verify($node->source())->equals('ini');
        verify($node->priority())->equals(10);
        verify($node->metadata())->equals(['origin' => 'ini']);
    }

    public function testSkipsMissingKeys(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10),
        ]);

        $next = new CastingProcessor(['missing' => 'int'])->process($graph);

        verify($next->has('missing'))->false();
    }

    public function testThrowsOnUncastableValue(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', 'not-a-number', 'ini', 10),
        ]);

        $this->expectException(CastException::class);

        new CastingProcessor(['port' => 'int'])->process($graph);
    }

    public function testThrowsOnUnknownType(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('port', '8080', 'ini', 10),
        ]);

        $this->expectException(CastException::class);

        new CastingProcessor(['port' => 'array'])->process($graph);
    }

    private function cast(string $type, mixed $value): mixed
    {
        $graph = new ConfigurationGraph([new ConfigurationNode('k', $value, 'ini', 10)]);

        return new CastingProcessor(['k' => $type])->process($graph)->node('k')->value();
    }

    /**
     * The full accepted coercion matrix: untyped sources (INI, ENV, XML) hand in
     * strings, so casting must be lenient in exactly these cases.
     *
     * @dataProvider castMatrix
     */
    public function testCastMatrix(string $type, mixed $input, mixed $expected, string $expectedType): void
    {
        $actual = $this->cast($type, $input);

        verify($actual)->equals($expected);
        verify(\get_debug_type($actual))->equals($expectedType);
    }

    /**
     * @return array<string, array{string, mixed, mixed, string}>
     */
    public function castMatrix(): array
    {
        return [
            'int from int'             => ['int', 7, 7, 'int'],
            'int from negative int'    => ['int', -7, -7, 'int'],
            'int truncates float'      => ['int', 7.9, 7, 'int'],
            'int truncates neg float'  => ['int', -7.9, -7, 'int'],
            'int from numeric string'  => ['int', '8080', 8080, 'int'],
            'int from signed string'   => ['int', '-8080', -8080, 'int'],
            'int from zero string'     => ['int', '0', 0, 'int'],
            'int from exponent string' => ['int', '1e3', 1000, 'int'],

            'float from int'           => ['float', 2, 2.0, 'float'],
            'float from float'         => ['float', 0.5, 0.5, 'float'],
            'float from float string'  => ['float', '0.5', 0.5, 'float'],
            'float from int string'    => ['float', '2', 2.0, 'float'],
            'float from signed string' => ['float', '-0.25', -0.25, 'float'],

            'bool from true'           => ['bool', true, true, 'bool'],
            'bool from false'          => ['bool', false, false, 'bool'],
            'bool from int one'        => ['bool', 1, true, 'bool'],
            'bool from int zero'       => ['bool', 0, false, 'bool'],
            'bool from "true"'         => ['bool', 'true', true, 'bool'],
            'bool from "TRUE"'         => ['bool', 'TRUE', true, 'bool'],
            'bool from padded "yes"'   => ['bool', '  yes  ', true, 'bool'],
            'bool from "on"'           => ['bool', 'on', true, 'bool'],
            'bool from "y"'            => ['bool', 'y', true, 'bool'],
            'bool from "1"'            => ['bool', '1', true, 'bool'],
            'bool from "false"'        => ['bool', 'false', false, 'bool'],
            'bool from "off"'          => ['bool', 'off', false, 'bool'],
            'bool from "no"'           => ['bool', 'no', false, 'bool'],
            'bool from "n"'            => ['bool', 'n', false, 'bool'],
            'bool from "0"'            => ['bool', '0', false, 'bool'],

            'string from string'       => ['string', 'api', 'api', 'string'],
            'string from empty string' => ['string', '', '', 'string'],
            'string from int'          => ['string', 8080, '8080', 'string'],
            'string from float'        => ['string', 0.5, '0.5', 'string'],
            'string from true'         => ['string', true, '1', 'string'],
            'string from false'        => ['string', false, '', 'string'],
        ];
    }

    /**
     * @dataProvider failedCastMatrix
     */
    public function testRejectsUncastableValues(string $type, mixed $input, string $expectedMessage): void
    {
        try {
            $this->cast($type, $input);
            self::fail(sprintf('Expected the %s cast to fail.', $type));
        } catch (CastException $e) {
            verify($e->getMessage())->equals($expectedMessage);
        }
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public function failedCastMatrix(): array
    {
        return [
            // A fractional string is never silently truncated to int.
            'int rejects float string'  => ['int', '4.2', 'Cannot cast key "k" to int.'],
            'int rejects word'          => ['int', 'not-a-number', 'Cannot cast key "k" to int.'],
            'int rejects empty string'  => ['int', '', 'Cannot cast key "k" to int.'],
            'int rejects true'          => ['int', true, 'Cannot cast key "k" to int.'],
            'int rejects null'          => ['int', null, 'Cannot cast key "k" to int.'],
            'int rejects array'         => ['int', [1], 'Cannot cast key "k" to int.'],

            'float rejects word'        => ['float', 'abc', 'Cannot cast key "k" to float.'],
            'float rejects empty'       => ['float', '', 'Cannot cast key "k" to float.'],
            'float rejects true'        => ['float', true, 'Cannot cast key "k" to float.'],
            'float rejects null'        => ['float', null, 'Cannot cast key "k" to float.'],
            'float rejects array'       => ['float', [], 'Cannot cast key "k" to float.'],

            // Only the exact ints 1/0 map to booleans.
            'bool rejects int two'      => ['bool', 2, 'Cannot cast key "k" to bool.'],
            'bool rejects float one'    => ['bool', 1.0, 'Cannot cast key "k" to bool.'],
            'bool rejects word'         => ['bool', 'maybe', 'Cannot cast key "k" to bool.'],
            'bool rejects empty string' => ['bool', '', 'Cannot cast key "k" to bool.'],
            'bool rejects null'         => ['bool', null, 'Cannot cast key "k" to bool.'],
            'bool rejects array'        => ['bool', [], 'Cannot cast key "k" to bool.'],

            'string rejects null'       => ['string', null, 'Cannot cast key "k" to string.'],
            'string rejects array'      => ['string', ['a'], 'Cannot cast key "k" to string.'],
            'string rejects object'     => ['string', new \stdClass(), 'Cannot cast key "k" to string.'],

            'unknown type'              => ['datetime', 'now', 'Unknown cast type "datetime" for key "k".'],
            'empty type'                => ['', 'x', 'Unknown cast type "" for key "k".'],
            'wrong case type'           => ['INT', '1', 'Unknown cast type "INT" for key "k".'],
        ];
    }

    public function testUncastKeysKeepNodeIdentity(): void
    {
        $node = new ConfigurationNode('untouched', ['deep' => true], 'json', 10);
        $graph = new ConfigurationGraph([$node, new ConfigurationNode('port', '80', 'ini', 10)]);

        $next = new CastingProcessor(['port' => 'int'])->process($graph);

        self::assertSame($node, $next->node('untouched'));
        verify($next->node('port')->value())->equals(80);
    }

    public function testStageNameDefaultsToCasting(): void
    {
        verify(new CastingProcessor([])->stageName())->equals(ProcessorStage::Casting->value);
    }

    public function testStageNameIsOverridable(): void
    {
        $processor = new CastingProcessor([], ProcessorStage::Validation);

        verify($processor->stageName())->equals(ProcessorStage::Validation->value);
    }

    public function testEmptyCastMapLeavesTheGraphUntouched(): void
    {
        $graph = new ConfigurationGraph([
            new ConfigurationNode('a', '1', 'ini', 10),
            new ConfigurationNode('b', 'two', 'ini', 10),
        ]);

        $next = new CastingProcessor([])->process($graph);

        verify($next->node('a')->value())->equals('1');
        verify($next->node('b')->value())->equals('two');
        verify(\array_keys($next->nodes()))->equals(['a', 'b']);
    }
}
