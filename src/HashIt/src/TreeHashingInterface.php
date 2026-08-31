<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;

/**
 * Tree (chunked) hashing — split a large input into fixed-size chunks, hash each
 * chunk independently, then combine the chunk digests with a stable, specified
 * combiner into a single root digest that is *distinct* from the plain
 * {@see HasherInterface::hash()} of the same bytes.
 *
 * The combiner and framing are specified in docs/ADR-0001-tree-hash-combiner.md
 * and implemented by {@see TreeHasher}. A tree hash is a function of
 * (input bytes, chunkSize, algorithm); callers MUST record chunkSize alongside
 * the digest, because a different chunkSize yields a different tree hash.
 */
interface TreeHashingInterface
{
    /**
     * Compute the tree hash of $input.
     *
     * When $chunkSize is greater than or equal to the input length the result is
     * identical to {@see HasherInterface::hash()} (single chunk, no combination),
     * so treeHash is a strict generalization of hash().
     *
     * @param HashInputInterface $input byte source (string / stream / file / composite)
     * @param int $chunkSize positive chunk size in bytes
     * @param string|HashAlgorithm $algorithm base algorithm for leaves and internal nodes
     *
     * @throws InvalidArgumentException when $chunkSize is not positive
     */
    public function treeHash(
        HashInputInterface $input,
        int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): HashInterface;
}
