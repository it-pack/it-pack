<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use Go\VirtualFileSystem\FileSystem;

final class VirtualFileSystemExtension extends Extension
{
    public static array $events = [
        Events::TEST_BEFORE => 'beforeTest',
        Events::TEST_AFTER  => 'afterTest',
    ];

    private ?FileSystem $fileSystem = null;

    public function beforeTest(TestEvent $event): void
    {
        $this->fileSystem = FileSystem::mount();
    }

    public function afterTest(TestEvent $event): void
    {
        $this->fileSystem?->unmount();
        $this->fileSystem = null;
    }
}
