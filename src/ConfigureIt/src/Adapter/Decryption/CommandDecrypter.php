<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Decryption;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;
use ItPack\ConfigureIt\Contract\DecrypterInterface;
use ItPack\ConfigureIt\Exception\DecryptionException;

/**
 * Base decrypter that shells out to an external command via a
 * {@see CommandRunnerInterface}.
 *
 * The ciphertext is fed to the command on stdin; stdout is the clear-text
 * secret. Adapters ({@see AgeDecrypter}, {@see SopsDecrypter}) supply the
 * concrete command and reuse this implementation, so vendor specifics stay in
 * the adapter and never reach Core (M4 — Epic E5.1).
 */
abstract class CommandDecrypter implements DecrypterInterface
{
    /**
     * @param list<string> $command the base command (without the ciphertext).
     */
    public function __construct(
        protected CommandRunnerInterface $runner,
        protected array                 $command,
    ) {
    }

    public function decrypt(string $ciphertext): string
    {
        try {
            $output = $this->runner->run([...$this->command], $ciphertext);
        } catch (\Throwable $e) {
            throw new DecryptionException(
                sprintf('Failed to decrypt secret: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        return rtrim($output, "\r\n");
    }
}
