<?php

declare(strict_types=1);

namespace ItPack\HashIt;

interface HmacHasherInterface
{
    public function hmac(string $data, string $key, string|HashAlgorithm $algorithm = 'sha256'): HashInterface;
}
