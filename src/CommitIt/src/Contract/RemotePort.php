<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

use ItPack\CommitIt\Domain\Value\BranchName;
use ItPack\CommitIt\Domain\Value\RemoteName;

/**
 * Remote operations and synchronization.
 *
 * @api
 */
interface RemotePort
{
    public function addRemote(RemoteName $name, string $url): void;

    public function removeRemote(RemoteName $name): void;

    /**
     * @return array<string, string> map of remote name => url
     */
    public function listRemotes(): array;

    public function fetch(?RemoteName $remote = null): void;

    public function pull(?RemoteName $remote = null, ?BranchName $branch = null): void;

    public function push(?RemoteName $remote = null, ?BranchName $branch = null): void;
}
