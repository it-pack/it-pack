<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Shared;

/**
 * Central vocabulary for correlation-propagation keys shared across adapters.
 *
 * These string literals were duplicated across the PSR-7 and CloudEvents
 * adapters (request/response headers, the correlation context key, and the
 * CloudEvents correlation attribute). Centralizing them removes the risk of a
 * silent mismatch when only one copy is renamed (RunIt T3.4).
 *
 * Declared as an `interface` (constants only, never instantiated) so it carries
 * no behavioral surface and stays exempt from the project's `requireInterface`
 * rule — mirroring `ArtifactField`.
 *
 * @internal vocabulary only; no behavioral dependency on any adapter.
 */
interface CorrelationKeys
{
    public const string HTTP_CORRELATION_HEADER = 'X-Correlation-Id';

    public const string HTTP_EXECUTION_HEADER = 'X-Execution-Id';

    public const string CONTEXT_CORRELATION_KEY = 'ctx.correlation.id';

    public const string CLOUDEVENTS_CORRELATION_ATTRIBUTE = 'correlation.id';
}
