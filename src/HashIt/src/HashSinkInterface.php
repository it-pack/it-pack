<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * A byte sink that structured {@see HashInputInterface} implementations write
 * their canonical bytes into.
 *
 * {@see HashBuilder} is the one-way sink used at the top level (it finalizes to a
 * hash). {@see \ItPack\HashIt\HashInput\TempSink} is a measuring, rewindable sink
 * used by {@see \ItPack\HashIt\HashInput\CompositeInput} to length-prefix its
 * children — the framing needs a child's byte length *before* the bytes can be
 * appended, which a finalizing hash context cannot provide.
 */
interface HashSinkInterface
{
    public function append(string $data): HashSinkInterface;
}
