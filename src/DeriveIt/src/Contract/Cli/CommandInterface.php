<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Cli;

/**
 * Contract for a CLI command so the driver is substitutable / testable behind an
 * interface (F-4 / RP-4). {@see \ItPack\DeriveIt\Cli\DeriveCommand} is the
 * reference implementation; {@see \ItPack\DeriveIt\Cli\DeriveCliInput} (or a future
 * `bin/derive` entry) can dispatch any CommandInterface.
 */
interface CommandInterface
{
    /**
     * @param list<string> $args argv without the script name
     */
    public function execute(array $args): int;
}
