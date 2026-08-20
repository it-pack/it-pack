<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Adapter\Contract\ProcessorAdapterInterface;
use ItPack\ConfigureIt\Adapter\Contract\SchemaAdapterInterface;
use ItPack\ConfigureIt\Contract\SchemaInterface;
use Nette\Schema\Processor as NetteProcessor;

final readonly class Processor implements ProcessorAdapterInterface
{
    public function __construct(
        private NetteProcessor $adaptee = new NetteProcessor,
    ) {}

    public function skipDefaults(bool $value = true): void
    {
        $this->adaptee->skipDefaults($value);
    }

    public function process(SchemaAdapterInterface|SchemaInterface $schema, mixed $data): mixed
    {
        return $this->adaptee->process(
            $schema->getAdaptee(),
            $data
        );
    }

    public function processMultiple(SchemaAdapterInterface|SchemaInterface $schema, array $dataset): mixed
    {
        return $this->adaptee->processMultiple(
            $schema->getAdaptee(),
            $dataset
        );
    }

    public function getWarnings(): array
    {
        return $this->adaptee->getWarnings();
    }

    public function getAdaptee(): object
    {
        return $this->adaptee;
    }
}