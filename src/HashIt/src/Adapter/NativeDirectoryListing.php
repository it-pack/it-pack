<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use DirectoryIterator;
use ItPack\HashIt\DirectoryListingInterface;
use ItPack\HashIt\FilepathInterface;

final readonly class NativeDirectoryListing implements DirectoryListingInterface
{
    public function regularFiles(string $directory): array
    {
        $files = [];

        foreach (new DirectoryIterator($directory) as $item) {
            if ($item->isFile()) {
                $files[] = NativeFilepathFileinfo::of($item->getPathname());
            }
        }

        usort($files, static fn (FilepathInterface $a, FilepathInterface $b): int => strcmp((string) $a, (string) $b));

        return $files;
    }
}
