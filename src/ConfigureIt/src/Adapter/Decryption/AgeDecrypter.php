<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Decryption;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;

/**
 * Decrypts `age`-encrypted secrets.
 *
 * Wraps the `age --decrypt` CLI, reading the ciphertext from stdin and the
 * recipient identity from `$identityFile` (M4 — Epic E5.1).
 */
final class AgeDecrypter extends CommandDecrypter
{
    public function __construct(CommandRunnerInterface $runner, string $identityFile)
    {
        parent::__construct(
            $runner,
            ['age', '--decrypt', '-i', $identityFile, '-'],
        );
    }
}
