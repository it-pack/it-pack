<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Adapter\Common;

use ItPack\CommandIt\Contract\Port\SerializerInterface;

/**
 * No-op serializer: returns the payload unchanged (verbatim storage escape hatch).
 *
 * Use it when a consumer wants the factory default to NOT normalize payloads before
 * persistence. Prefer {@see SafeSerializer} for the safe-by-default primitive-only
 * snapshots (ADR 0007). The content type is declared as `application/json` for parity
 * with the safe serializer; the actual bytes remain the caller's responsibility.
 *
 * @api
 */
final class PassthroughSerializer implements SerializerInterface
{
    public function normalize(mixed $data): mixed
    {
        return $data;
    }

    public function contentType(): string
    {
        return 'application/json';
    }
}
