<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Go\VirtualFileSystem\FileSystem;
use RuntimeException;

trait VirtualFileSystem
{
    protected function virtualFileSystem(): FileSystem
    {
        $fileSystem = FileSystem::get('vfs');

        if (null === $fileSystem) {
            throw new RuntimeException('Virtual filesystem extension is not active.');
        }

        return $fileSystem;
    }

    protected function vfsPath(string $path = '/'): string
    {
        return $this->virtualFileSystem()->path($path);
    }
}
