<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter;
use ItPack\ConfigureIt\Adapter\Contract\ContextAdapterInterface;
use ItPack\ConfigureIt\Contract\ValueObject\ContextInterface;
use ItPack\ConfigureIt\Contract\ValueObject\SchemaInterface;
use Nette\Schema\Schema as NetteSchema;

final readonly class Schema implements SchemaInterface
{
    public function __construct(
        public NetteSchema $adaptee,
    ){}

    public function normalize(mixed $value, ContextAdapterInterface|ContextInterface $context): mixed
    {
        return $this->adaptee->normalize(
            $value,
            $context->getAdaptee()
        );
    }

    public function merge(mixed $value, mixed $base): mixed
    {
        return $this->adaptee->merge($value, $base);
    }

    public function complete(mixed $value, ContextAdapterInterface|ContextInterface $context): mixed
    {
        return $this->adaptee->complete(
            $value,
            $context->getAdaptee()
        );
    }

    public function completeDefault(ContextAdapterInterface|ContextInterface $context): mixed
    {
        return $this->adaptee->completeDefault(
            $context->getAdaptee()
        );
    }
}