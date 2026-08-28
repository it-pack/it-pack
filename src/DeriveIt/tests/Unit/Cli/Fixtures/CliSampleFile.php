<?php

declare(strict_types=1);

namespace Tests\Unit\Cli\Fixtures;

/**
 * Sample source file used by {@see DeriveCommandTest} to exercise the
 * tokenizer-based {@see \ItPack\DeriveIt\Cli\FileTargetResolver} path (CLI invoked
 * with a file path instead of a class name). Lives under a namespace that maps
 * onto the PSR-4 autoloader so the resolved FQCN is loadable.
 */
class CliSampleFile
{
    public function fromFile(): void
    {
    }
}
