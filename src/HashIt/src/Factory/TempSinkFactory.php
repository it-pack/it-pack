<?php

declare(strict_types=1);

namespace ItPack\HashIt\Factory;

use ItPack\HashIt\HashInput\TempSink;

/**
 * The single place in the package that may instantiate a {@see TempSink}
 * (the measuring, rewindable sink used by {@see TreeHasher} and
 * {@see HashInput\CompositeInput}). Callers inject this port and call
 * {@see self::create()} instead of `new TempSink()`, keeping the sink
 * creation surface auditable.
 *
 * Layer: Core.
 */
final class TempSinkFactory
{
    public function create(): TempSink
    {
        return new TempSink();
    }
}
