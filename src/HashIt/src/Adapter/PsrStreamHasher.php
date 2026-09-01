<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use InvalidArgumentException;
use ItPack\HashIt\Contract\ContextFactoryInterface;
use ItPack\HashIt\Contract\HasherInterface;
use ItPack\HashIt\Contract\HashInterface;
use ItPack\HashIt\Contract\StreamHasherInterface;
use ItPack\HashIt\Factory\HashFactory;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashBuilder;
use Psr\Http\Message\StreamInterface;

/**
 * Hashes a PSR-7 {@see StreamInterface} body in chunks without loading it fully
 * into memory. Reads incrementally via {@see StreamInterface::read()}; the stream
 * is never detached, so the caller can keep using it afterwards (e.g. the same
 * request body can be hashed *and* forwarded).
 *
 * SRI / base64 formatting of the resulting digest is OUT OF SCOPE here and lives
 * with the NF-C1 formatters — this adapter only produces a plain {@see HashInterface}.
 */
final class PsrStreamHasher implements StreamHasherInterface
{
    public function __construct(
        private readonly ContextFactoryInterface $factory,
        private readonly HashFactory $hashFactory,
    ) {
    }

    public function hash(
        StreamInterface $body,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
        int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES,
    ): HashInterface {
        if (!$body->isReadable()) {
            throw new InvalidArgumentException('PsrStreamHasher requires a readable stream');
        }

        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        $builder = HashBuilder::start($this->factory, $this->hashFactory, $algorithm);

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $chunk = $body->read($chunkSize);
            if ($chunk === '') {
                break;
            }
            $builder = $builder->append($chunk);
        }

        return $builder->digest();
    }
}
