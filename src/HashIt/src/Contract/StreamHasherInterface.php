<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

use ItPack\HashIt\HashAlgorithm;
use Psr\Http\Message\StreamInterface;

interface StreamHasherInterface
{
    public function hash(
        StreamInterface $stream,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES,
    ): HashInterface;
}
