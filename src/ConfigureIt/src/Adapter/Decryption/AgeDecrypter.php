<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Decryption;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;

/**
 * Decrypts `age`-encrypted secrets.
 *
 * Wraps the `age --decrypt` CLI, reading the ciphertext from stdin and the
 * recipient identity from `$identityFile` (M4 — Epic E5.1).
 */
final class AgeDecrypter implements DecrypterInterface
{
    private readonly CommandDecryption $decryption;

    public function __construct(CommandRunnerInterface $runner, string $identityFile)
    {
        $this->decryption = new CommandDecryption(
            $runner,
            ['age', '--decrypt', '-i', $identityFile, '-'],
        );
    }

    public function decrypt(string $ciphertext): string
    {
        return $this->decryption->decrypt($ciphertext);
    }
}
