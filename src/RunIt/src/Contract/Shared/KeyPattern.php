<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Shared;

/**
 * The single validated-key character pattern shared by all RunIt key rules.
 *
 * Namespaced keys (`tenant.id`, `http.status`) are made of letters, digits,
 * and the separators `.`, `-`, `_`. The pattern was previously duplicated in
 * every validator that enforced it; centralizing it removes the risk of one
 * copy being tightened while another silently keeps the old rule (Phase 2,
 * T2.1).
 *
 * Declared as an `interface` (constants only, never instantiated) so it carries
 * no behavioral surface and stays exempt from the project's `requireInterface`
 * rule — mirroring {@see CorrelationKeys} and `ArtifactField`.
 *
 * @internal vocabulary only; no behavioral dependency on any validator.
 */
interface KeyPattern
{
    public const string VALIDATED_KEY = '/^[A-Za-z0-9._-]++\z/';
}
