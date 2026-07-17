<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use HashContext;
use ItPack\HashIt\HashContextInterface;

final class NativeHashContext implements HashContextInterface
{
    private HashContext $context;

    public function __construct(string $algorithm)
    {
        $this->context = hash_init($algorithm);
    }

    public function update(string $data): void
    {
        hash_update($this->context, $data);
    }

    public function finalize(bool $binary): string
    {
        return hash_final($this->context, $binary);
    }
}
