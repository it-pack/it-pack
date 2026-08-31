<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * A structured hashing input. Implementations write their canonical (framed)
 * bytes into a {@see HashSinkInterface} via {@see self::writeTo()}; the framing
 * convention is defined by each concrete type and by
 * {@see \ItPack\HashIt\HashInput\CompositeInput} for ordered composition.
 *
 * Only the three allowed input kinds are supported (string, stream, file) —
 * there are deliberately no exotic inputs (see roadmap §7).
 */
interface HashInputInterface
{
    /**
     * Length of the little-endian child-length prefix {@see \ItPack\HashIt\HashInput\CompositeInput}
     * writes before each child's framed bytes.
     */
    public const int FRAME_LENGTH_BYTES = 8;

    /**
     * Append this input's canonical bytes to $sink and return the resulting sink.
     *
     * The builder/sink is immutable, so the returned instance is the source of
     * truth for any further appends. Concrete inputs append their *raw* bytes
     * here; length-prefix framing is applied only by {@see \ItPack\HashIt\HashInput\CompositeInput}.
     */
    public function writeTo(HashSinkInterface $sink): HashSinkInterface;
}
