<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Contract;

interface ProcessorInterface
{
    public function skipDefaults(bool $value = true): void;


    /**
     * Normalizes and validates data. Result is a clean completed data.
     * @throws ValidationExceptionInterface

     */
    public function process(SchemaInterface $schema, mixed $data): mixed;


    /**
     * Normalizes and validates and merges multiple data. The result is clean completed data.
     * @param  list<mixed>  $dataset
     * @throws ValidationExceptionInterface

     */
    public function processMultiple(SchemaInterface $schema, array $dataset): mixed;


    /** @return list<string> */
    public function getWarnings(): array;
}