<?php

declare(strict_types=1);

namespace ItPack\HashIt;

/**
 * Mutable, in-progress hash handle returned by
 * {@see ResumableHashingInterface::start()} and rebuilt by
 * {@see ResumableHashingInterface::resume()}. Not readonly: {@see self::update()}
 * and {@see self::checkpoint()} mutate / snapshot the underlying context.
 */
final readonly class ResumableHash implements ResumableHashInterface
{
    public function __construct(
        private HashContextInterface $context,
        private string               $algorithm,
        private bool                 $binary,
    ) {
    }

    public function update(string $data): void
    {
        $this->context->update($data);
    }

    public function checkpoint(): string
    {
        $state = $this->context->checkpoint();

        return serialize(['algorithm' => $this->algorithm, 'binary' => $this->binary, 'state' => $state]);
    }

    public function finalize(): HashInterface
    {
        return new Hash($this->context->finalize($this->binary), $this->algorithm, $this->binary);
    }
}
