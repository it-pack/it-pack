<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use SPLFileInfo;

readonly class FilepathFileinfo implements FilepathInterface
{
    public function __construct(private SPLFileInfo $fileInfo){}

    public function __toString(): string
    {
        return (string) $this->fileInfo;
    }
}