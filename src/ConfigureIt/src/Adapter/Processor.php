<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Adapter\Contract\ProcessorAdapterInterface;
use ItPack\ConfigureIt\Contract\SchemaInterface;
use Nette\Schema\Processor as NetteProcessor;
use Nette\Schema\Schema as NetteSchema;

final readonly class Processor implements ProcessorAdapterInterface
{
    public function __construct(
        private NetteProcessor $adaptee = new NetteProcessor(),
    ) {
    }

    public function skipDefaults(bool $value = true): void
    {
        $this->adaptee->skipDefaults($value);
    }

    public function process(SchemaInterface $schema, mixed $data): mixed
    {
        /** @var NetteSchema $adaptee */
        $adaptee = $schema->getAdaptee();

        return $this->adaptee->process($adaptee, $data);
    }

    public function processMultiple(SchemaInterface $schema, array $dataset): mixed
    {
        /** @var NetteSchema $adaptee */
        $adaptee = $schema->getAdaptee();

        return $this->adaptee->processMultiple($adaptee, $dataset);
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
