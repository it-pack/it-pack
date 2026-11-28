<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use ItPack\HashIt\HashAlgorithm;

interface URNBuilderInterface
{
    public function withHash(HashInterface $hash): self;

    public function withHex(string $hex): self;

    public function withAlgorithm(HashAlgorithm|string $algorithm): self;

    public function build(): string;
}
