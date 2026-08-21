<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Codegen;

/**
 * Marker implemented by every DTO emitted by {@see ConfigCodegen}.
 *
 * Lets {@see ConfigDtoHydrator} return a statically-known type so consumers (and
 * static analysis) can call {@see toArray()} without reaching for reflection.
 *
 * @see docs/architecture/MODEL.md "Typed Snapshot" (codegen companion, E2.3)
 */
interface GeneratedConfigDtoInterface
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
