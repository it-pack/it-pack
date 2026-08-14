<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Shared;

use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;

/**
 * A safe, immutable description of a thrown error or exception.
 *
 * The description holds only structural, serializable facts and never a
 * reference to the original `Throwable`. Nested previous causes are themselves
 * descriptions, so the whole structure is recursive yet throwable-free and
 * bounded by the mapper that built it.
 *
 * Construction is intentionally primitive: {@see self::create()} takes the
 * captured values directly. The mapper — not this value object — owns the
 * policy for what to capture and how far to recurse.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ThrowableDescription
 */
final readonly class ThrowableDescription implements ThrowableDescriptionInterface
{
    private function __construct(
        private readonly string $class,
        private readonly string $message,
        private readonly int $code,
        private readonly ?ThrowableDescriptionInterface $previous,
        private readonly ?string $trace,
    ) {
    }

    /**
     * Creates a description from already-captured primitive values.
     */
    public static function create(
        string $class,
        string $message,
        int $code,
        ?ThrowableDescriptionInterface $previous,
        ?string $trace,
    ): self {
        return new self($class, $message, $code, $previous, $trace);
    }

    public function class(): string
    {
        return $this->class;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function code(): int
    {
        return $this->code;
    }

    public function previous(): ?ThrowableDescriptionInterface
    {
        return $this->previous;
    }

    public function trace(): ?string
    {
        return $this->trace;
    }

    /**
     * @return array<string, string|int|array<array-key, mixed>|null>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'code' => $this->code,
            'previous' => $this->previous?->toArray(),
            'trace' => $this->trace,
        ];
    }
}
