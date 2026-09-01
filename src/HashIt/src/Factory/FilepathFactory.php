<?php

declare(strict_types=1);

namespace ItPack\HashIt\Factory;

use ItPack\HashIt\Filepath;

/**
 * The single place in the package that may instantiate the plain
 * {@see Filepath} value object (a {@see Contract\FilepathInterface}
 * adapter the core can construct without reaching into the
 * {@see \ItPack\HashIt\Adapter} layer). Callers inject this port and
 * call {@see self::create()} instead of `new Filepath(…)`, keeping the
 * value-object creation surface auditable.
 *
 * Layer: Core.
 */
final class FilepathFactory
{
    public function create(string $path): Filepath
    {
        return new Filepath($path);
    }
}
