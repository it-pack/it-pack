<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\Contract\ConfigurationGraphInterface;
use ItPack\ConfigureIt\Contract\ConfigurationSchemaInterface;
use ItPack\ConfigureIt\Core\ConfigurationGraph;
use ItPack\ConfigureIt\Core\ConfigurationNode;
use ItPack\ConfigureIt\Core\ValidationProcessor;
use ItPack\ConfigureIt\Exception\ValidationException;
use ItPack\ConfigureIt\ValueObject\ValidationResult;

class ValidationProcessorTest extends Unit
{
    public function testPassesValidGraphThrough(): void
    {
        $graph = new ConfigurationGraph([new ConfigurationNode('a', 1, 'base', 10)]);

        $next = new ValidationProcessor($this->schema(true))->process($graph);

        verify($next->node('a')->value())->equals(1);
        verify($next->node('a')->provenance()->validatedBy())->notNull();
    }

    public function testThrowsOnInvalidGraph(): void
    {
        $this->expectException(ValidationException::class);

        new ValidationProcessor($this->schema(false))->process(
            new ConfigurationGraph([new ConfigurationNode('a', 1, 'base', 10)]),
        );
    }

    private function schema(bool $valid): ConfigurationSchemaInterface
    {
        return new class ($valid) implements ConfigurationSchemaInterface {
            public function __construct(private bool $valid)
            {
            }

            public function validate(ConfigurationGraphInterface $graph): ValidationResult
            {
                return $this->valid
                    ? new ValidationResult(true)
                    : new ValidationResult(false, [['path' => 'a', 'message' => 'must be string', 'severity' => 'error']]);
            }
        };
    }
}
