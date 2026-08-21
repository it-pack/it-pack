<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Decryption;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;

/**
 * Decrypts SOPS-encrypted secrets.
 *
 * Wraps the `sops --decrypt` CLI, reading the ciphertext from stdin (M4 — Epic
 * E5.1).
 */
final class SopsDecrypter extends CommandDecrypter
{
    public function __construct(CommandRunnerInterface $runner)
    {
        parent::__construct(
            $runner,
            ['sops', '--decrypt', '-'],
        );
    }
}
