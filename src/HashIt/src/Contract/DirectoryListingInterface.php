<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

interface DirectoryListingInterface
{
    /**
     * @return list<FilepathInterface> regular files directly inside $directory, sorted by path
     */
    public function regularFiles(string $directory): array;
}
