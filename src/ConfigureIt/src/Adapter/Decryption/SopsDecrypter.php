<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Decryption;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;

/**
 * Decrypts SOPS-encrypted secrets.
 *
 * Wraps the `sops --decrypt` CLI, reading the ciphertext from stdin (M4 — Epic
 * E5.1).
 */
final class SopsDecrypter implements DecrypterInterface
{
    private readonly CommandDecryption $decryption;

    public function __construct(CommandRunnerInterface $runner)
    {
        $this->decryption = new CommandDecryption(
            $runner,
            ['sops', '--decrypt', '-'],
        );
    }

    public function decrypt(string $ciphertext): string
    {
        return $this->decryption->decrypt($ciphertext);
    }
}
