<?php

declare(strict_types=1);

namespace ItPack\HashIt\Contract;

/**
 * Incremental hashing state for one algorithm instance.
 *
 * Lifecycle: an instance accepts update() calls until finalize() consumes its
 * state. Any further update(), finalize() or copy() on the same instance fails
 * (native engines raise an \Error). Call copy() before finalize() to branch
 * the state.
 *
 * Implementations backed by other technologies must behave equivalently or
 * document their deviation from this contract.
 */
interface HashContextInterface
{
    public function update(string $data): void;

    public function finalize(bool $binary): string;

    public function copy(): HashContextInterface;

    /**
     * Return a portable serialization of the current state without consuming it,
     * so the live handle may keep being updated after the call. The blob can be
     * restored via {@see ContextFactoryInterface::restore()}.
     *
     * Throws \RuntimeException when the backing engine cannot serialize the state
     * (e.g. keyed / HMAC contexts, which the native hash extension refuses to
     * serialize).
     */
    public function checkpoint(): string;
}
