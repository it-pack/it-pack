<?php

declare(strict_types=1);

namespace ItPack\HashIt\HashInput;

use ItPack\HashIt\Contract\FilepathInterface;
use ItPack\HashIt\Contract\HashInputInterface;
use ItPack\HashIt\Contract\HashSinkInterface;
use RuntimeException;

final readonly class FileInput implements HashInputInterface
{
    public function __construct(private FilepathInterface $path)
    {
    }

    public function writeTo(HashSinkInterface $sink): HashSinkInterface
    {
        $file = (string) $this->path;

        if (!is_file($file)) {
            throw new RuntimeException('Unable to read file: ' . $file);
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read file: ' . $file);
        }

        try {
            return new StreamInput($handle)->writeTo($sink);
        } finally {
            fclose($handle);
        }
    }
}
