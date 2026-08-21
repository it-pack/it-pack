<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for a context classification tier.
 *
 * A classification decides the default export, propagation, and serialization
 * behavior of a context entry. Classification is independent of authorization:
 * it is a default applied at the edge, not an access-control decision.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ExecutionContext
 */
interface ContextClassificationInterface
{
    /**
     * Returns the stable, lower-case classification name (for example `public`).
     */
    public function label(): string;

    /**
     * Returns whether a value with this classification may leave the process
     * through external publication by default.
     */
    public function isExportable(): bool;

    /**
     * Returns whether a value with this classification may be propagated to
     * child executions or correlated work by default.
     */
    public function isPropagatable(): bool;

    /**
     * Returns whether a value with this classification may be serialized into
     * an immutable snapshot by default.
     */
    public function isSerializable(): bool;
}
