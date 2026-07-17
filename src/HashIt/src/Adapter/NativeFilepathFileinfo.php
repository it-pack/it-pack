<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use ItPack\HashIt\FilepathInterface;
use SplFileInfo;

final readonly class NativeFilepathFileinfo implements FilepathInterface
{
    public function __construct(private SplFileInfo $fileInfo)
    {
    }

    public function __toString(): string
    {
        return (string) $this->fileInfo;
    }
}
