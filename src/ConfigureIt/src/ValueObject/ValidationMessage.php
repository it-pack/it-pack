<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\ValueObject;

use ItPack\ConfigureIt\Contract\MessageInterface;

/**
 * Core-friendly validation message backed by a path/message pair.
 *
 * Implements {@see MessageInterface} so it can be carried by
 * {@see \ItPack\ConfigureIt\Contract\ValidationExceptionInterface} without
 * leaking the Nette adapter's message type into Core.
 */
final readonly class ValidationMessage implements MessageInterface
{
    public function __construct(
        public string $path,
        public string $message,
    ) {
    }

    public function toString(): string
    {
        return '' === $this->path
            ? $this->message
            : sprintf('%s: %s', $this->path, $this->message);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
