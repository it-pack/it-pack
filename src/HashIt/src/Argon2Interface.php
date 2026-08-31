<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Argon2 port. Argon2 is verify-oriented rather than derive-shaped: it folds the
 * salt and cost parameters into an opaque, self-describing hash string and is
 * checked with {@see Argon2Interface::verify()} — there is no separate "derive a
 * raw key of length N" step as with HKDF/PBKDF2.
 */
interface Argon2Interface
{
    /**
     * Hash a password with Argon2id, folding the salt and cost parameters into
     * the returned string. Passing $options overrides the sane defaults
     * (memory_cost / time_cost / threads).
     */
    public function hash(string $password, array $options = []): string;

    /**
     * Constant-time verification of $password against a previously produced hash.
     */
    public function verify(string $password, string $hash): bool;
}
