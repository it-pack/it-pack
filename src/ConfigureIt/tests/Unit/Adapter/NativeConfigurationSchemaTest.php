<?php

declare(strict_types=1);

namespace Tests\Unit\Adapter;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Adapter\NativeConfigurationSchema;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use Nette\Schema\Expect;

class NativeConfigurationSchemaTest extends Unit
{
    /**
     * @param array<string, mixed> $data
     */
    private function graphWith(array $data): ConfigurationGraph
    {
        $nodes = [];

        foreach ($data as $key => $value) {
            $nodes[] = new ConfigurationNode($key, $value, 'base', 10);
        }

        return new ConfigurationGraph($nodes);
    }

    public function testValidatesAgainstExpect(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'app' => Expect::structure(['debug' => Expect::bool()]),
        ])->castTo('array'));

        $result = $schema->validate($this->graphWith(['app' => ['debug' => true]]));

        verify($result->valid())->true();
        verify($result->errors())->equals([]);
    }

    public function testReportsErrorsForInvalidData(): void
    {
        $schema = new NativeConfigurationSchema(Expect::structure([
            'app' => Expect::structure(['debug' => Expect::bool()]),
        ])->castTo('array'));

        $result = $schema->validate($this->graphWith(['app' => ['debug' => 'not-bool']]));

        verify($result->valid())->false();
        verify($result->errors())->notEmpty();
    }
}
