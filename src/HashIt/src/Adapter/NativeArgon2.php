<?php

declare(strict_types=1);

namespace ItPack\HashIt\Adapter;

use ItPack\HashIt\Contract\Argon2Interface;

/**
 * Native Argon2id adapter over the PHP {@see password_hash} / {@see password_verify}
 * password extension functions.
 */
final class NativeArgon2 implements Argon2Interface
{
    public function hash(string $password, array $options = []): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options($options));
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * @param array<string, int> $options
     * @return array<string, int>
     */
    private function options(array $options): array
    {
        return array_merge([
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ], $options);
    }
}
