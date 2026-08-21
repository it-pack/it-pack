<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Context;

use ItPack\RunIt\Contract\Domain\ContextClassificationInterface;

/**
 * The closed set of context classification tiers.
 *
 * Each tier defines default export, propagation, and serialization behavior.
 * Classification is independent of authorization: it is a default applied at
 * the edge, not an access-control decision.
 *
 * Default behavior:
 *
 *  - `Public`       — exportable, propagatable, serializable.
 *  - `Internal`     — not exportable; propagatable and serializable.
 *  - `Confidential` — not exportable, not propagatable; serializable.
 *  - `Restricted`   — not exportable, not propagatable; serializable, but masked
 *                     on redaction.
 *  - `Secret`       — nothing by default; never serialized, never exported.
 *
 * `Restricted` and `Secret` differ in the redaction policy: restricted values
 * are masked (kept under a redaction marker) while secret values are dropped.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
enum ContextClassification: string implements ContextClassificationInterface
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';
    case Secret = 'secret';

    public function label(): string
    {
        return $this->value;
    }

    public function isExportable(): bool
    {
        return $this === self::Public;
    }

    public function isPropagatable(): bool
    {
        return $this === self::Public || $this === self::Internal;
    }

    public function isSerializable(): bool
    {
        return $this !== self::Secret;
    }
}
