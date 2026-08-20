<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;
use Nette\Schema\Context;

interface SchemaInterface
{
    /**
     * Normalization.
     * @param mixed $value
     * @param ContextInterface $context
     * @return mixed
     */
    public function normalize(mixed $value, ContextInterface $context): mixed;

    /**
     * Merging.
     * @param mixed $value
     * @param mixed $base
     * @return mixed
     */
    public function merge(mixed $value, mixed $base): mixed;

    /**
     * Validation and finalization.
     * @param mixed $value
     * @param ContextInterface $context
     * @return mixed
     */
    public function complete(mixed $value, ContextInterface $context): mixed;

    /**
     * @param ContextInterface $context
     * @return mixed
     */
    public function completeDefault(ContextInterface $context): mixed;

    /**
     * Returns the underlying schema implementation (adapter-specific).
     *
     * @return object
     */
    public function getAdaptee(): object;
}