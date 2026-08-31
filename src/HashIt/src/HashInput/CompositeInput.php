<?php

declare(strict_types=1);

namespace ItPack\HashIt\HashInput;

use ItPack\HashIt\HasherInterface;
use ItPack\HashIt\HashInputInterface;
use ItPack\HashIt\HashSinkInterface;

/**
 * An ordered collection of {@see HashInputInterface} children, framed so that the
 * same logical sequence always digests identically regardless of how it was
 * assembled, while a different order (or different boundaries) digests differently.
 *
 * Framing: for each child, write an 8-byte little-endian unsigned length
 * ({@see HashInputInterface::FRAME_LENGTH_BYTES}) followed by the child's own
 * framed bytes. Children are framed recursively, so nesting is canonical too.
 */
final readonly class CompositeInput implements HashInputInterface
{
    /**
     * @param list<HashInputInterface> $children
     */
    public function __construct(private array $children)
    {
    }

    public function writeTo(HashSinkInterface $sink): HashSinkInterface
    {
        $current = $sink;

        foreach ($this->children as $child) {
            $frame = new TempSink();
            $child->writeTo($frame);
            $length = $frame->length();

            $current = $current->append($this->lengthPrefix($length));

            foreach ($frame->chunks(HasherInterface::DEFAULT_CHUNK_SIZE_BYTES) as $chunk) {
                $current = $current->append($chunk);
            }
        }

        return $current;
    }

    /**
     * Encode $length as a {@see HashInputInterface::FRAME_LENGTH_BYTES}-byte
     * unsigned little-endian prefix. The constant is the single source of truth
     * for the frame width.
     */
    private function lengthPrefix(int $length): string
    {
        $bytes = '';
        $remaining = $length;

        for ($i = 0; $i < HashInputInterface::FRAME_LENGTH_BYTES; $i++) {
            $bytes .= chr($remaining & 0xFF);
            $remaining >>= 8;
        }

        return $bytes;
    }
}
