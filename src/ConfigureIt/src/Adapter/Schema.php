<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;

use ItPack\ConfigureIt\Contract\ContextInterface;
use ItPack\ConfigureIt\Contract\SchemaInterface;
use Nette\Schema\Context as NetteContext;
use Nette\Schema\Schema as NetteSchema;

final readonly class Schema implements SchemaInterface
{
    public function __construct(
        public NetteSchema $adaptee,
    ) {}

    public function normalize(mixed $value, ContextInterface $context): mixed
    {
        /** @var NetteContext $netteContext */
        $netteContext = $context->getAdaptee();

        return $this->adaptee->normalize($value, $netteContext);
    }

    public function merge(mixed $value, mixed $base): mixed
    {
        return $this->adaptee->merge($value, $base);
    }

    public function complete(mixed $value, ContextInterface $context): mixed
    {
        /** @var NetteContext $netteContext */
        $netteContext = $context->getAdaptee();

        return $this->adaptee->complete($value, $netteContext);
    }

    public function completeDefault(ContextInterface $context): mixed
    {
        /** @var NetteContext $netteContext */
        $netteContext = $context->getAdaptee();

        return $this->adaptee->completeDefault($netteContext);
    }

    public function getAdaptee(): NetteSchema
    {
        return $this->adaptee;
    }
}
