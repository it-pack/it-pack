<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use HashContext;

readonly class HashUpdater implements HashUpdaterInterface
{
    public function __construct(private HashContext $context)
    {

    }

    public function update(string $data): void
    {
        hash_update($this->context, $data);
    }
}