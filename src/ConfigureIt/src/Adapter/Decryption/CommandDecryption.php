<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Adapter\Decryption;

use ItPack\ConfigureIt\Contract\CommandRunnerInterface;
use ItPack\ConfigureIt\Exception\DecryptionException;
use Throwable;

/**
 * Shared decryption behavior for adapters that shell out to an external
 * command via a {@see CommandRunnerInterface}.
 *
 * The ciphertext is fed to the command on stdin; stdout is the clear-text
 * secret. Composed by {@see AgeDecrypter} and {@see SopsDecrypter}, so vendor
 * specifics stay in the adapter and never reach Core (M4 — Epic E5.1).
 */
final readonly class CommandDecryption
{
    /**
     * @param list<string> $command the base command (without the ciphertext).
     */
    public function __construct(
        private CommandRunnerInterface $runner,
        private array                  $command,
    ) {
    }

    public function decrypt(string $ciphertext): string
    {
        try {
            $output = $this->runner->run([...$this->command], $ciphertext);
        } catch (Throwable $e) {
            throw new DecryptionException(
                sprintf('Failed to decrypt secret: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        return rtrim($output, "\r\n");
    }
}
