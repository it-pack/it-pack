<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Contract;

interface WorkingTreePort
{
    public function getWorkingTree(): WorkingTree;
}
