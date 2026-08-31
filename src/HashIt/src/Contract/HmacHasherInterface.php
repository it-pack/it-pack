<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use ItPack\HashIt\HashAlgorithm;

interface HmacHasherInterface
{
    public function hmac(string $data, string $key, string|HashAlgorithm $algorithm = HashAlgorithm::SHA256): HashInterface;

    /**
     * The algorithm catalogue this hasher was composed with (extensible via a
     * custom {@see \ItPack\HashIt\Contract\AlgorithmSourceInterface}).
     *
     * @return list<HashAlgorithm>
     */
    public function algorithms(): array;

    public function supports(string $identifier): bool;

    public function resolve(string $identifier): HashAlgorithm;
}
