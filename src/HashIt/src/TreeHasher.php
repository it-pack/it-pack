<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use InvalidArgumentException;
use ItPack\HashIt\HashInput\TempSink;

/**
 * Reference implementation of {@see Contract\TreeHashingInterface}.
 *
 * Scheme (see docs/ADR-0001-tree-hash-combiner.md):
 *   1. Capture $input's raw bytes via {@see Contract\HashInputInterface::writeTo} into a
 *      rewindable {@see TempSink}, then read them back in $chunkSize windows.
 *   2. Each leaf = raw binary digest H(chunk) (outputBits/8 bytes).
 *   3. Combine leaves pairwise: node = H( frame(left) || frame(right) ), where
 *      frame(x) = LE64(length(x)) || x and x is a raw binary digest. The trailing
 *      odd node at any level is promoted unchanged (THEX-style). Recurse until a
 *      single root remains.
 *   4. A single chunk returns the plain hash() (hex), so treeHash is a strict
 *      generalization of hash().
 *
 * Built only with {@see HashBuilder} and {@see Contract\HashInputInterface} framing
 * (NF-B3); the core depends on nothing but itself (deptrac-clean).
 */
final readonly class TreeHasher implements Contract\TreeHashingInterface
{
    public function __construct(private Contract\ContextFactoryInterface $factory)
    {
    }

    public function treeHash(
        Contract\HashInputInterface $input,
        int $chunkSize = Contract\HasherInterface::DEFAULT_CHUNK_SIZE_BYTES,
        string|HashAlgorithm $algorithm = HashAlgorithm::SHA256,
    ): Contract\HashInterface {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        $algorithmName = $algorithm instanceof HashAlgorithm ? $algorithm->value : $algorithm;

        $sink = new TempSink();
        $input->writeTo($sink);

        $leaves = [];
        foreach ($sink->chunks($chunkSize) as $chunk) {
            $leaves[] = $this->rawHash($chunk, $algorithmName);
        }

        if ($leaves === []) {
            $leaves = [$this->rawHash('', $algorithmName)];
        }

        $root = $this->combine($leaves, $algorithmName);

        return Hash::create(bin2hex($root), $algorithmName, false);
    }

    /**
     * Recursive binary reduction over raw binary digests (each outputBits/8 bytes).
     *
     * @param list<string> $nodes
     */
    private function combine(array $nodes, string $algorithm): string
    {
        if (count($nodes) === 1) {
            return $nodes[0];
        }

        $next = [];
        $count = count($nodes);
        for ($i = 0; $i < $count; $i += 2) {
            if ($i + 1 < $count) {
                $next[] = $this->rawHash(
                    $this->frame($nodes[$i]) . $this->frame($nodes[$i + 1]),
                    $algorithm,
                );
            } else {
                $next[] = $nodes[$i];
            }
        }

        return $this->combine($next, $algorithm);
    }

    private function frame(string $digest): string
    {
        return $this->lengthPrefix(strlen($digest)) . $digest;
    }

    /**
     * 8-byte unsigned little-endian length prefix (mirrors
     * {@see Contract\HashInputInterface::FRAME_LENGTH_BYTES}).
     */
    private function lengthPrefix(int $length): string
    {
        $bytes = '';
        $remaining = $length;

        for ($i = 0; $i < Contract\HashInputInterface::FRAME_LENGTH_BYTES; $i++) {
            $bytes .= chr($remaining & 0xFF);
            $remaining >>= 8;
        }

        return $bytes;
    }

    /**
     * Raw (binary) digest of $data with $algorithm.
     */
    private function rawHash(string $data, string $algorithm): string
    {
        return HashBuilder::start($this->factory, $algorithm, true)
            ->append($data)
            ->digest()
            ->value()
        ;
    }
}
