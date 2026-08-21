<?php

declare(strict_types=1);

/**
 * RunIt — context classification.
 *
 * Execution context carries per-execution metadata as classified, bounded entries. Classification
 * decides the default export/propagation/serialization behavior and is not an access-control decision.
 * Secret values are deny-by-default: never exported, never serialized. See docs/concepts/context.md.
 *
 * Run from the package root: `php examples/context-classification.php`
 */

require __DIR__ . '/../vendor/autoload.php';

use ItPack\RunIt\Domain\Context\ContextEntry;
use ItPack\RunIt\Domain\Context\ContextKey;
use ItPack\RunIt\Domain\Context\ExecutionContext;

$context = ExecutionContext::empty()
    ->with(ContextEntry::public(ContextKey::fromString('environment'), 'prod'))
    ->with(ContextEntry::internal(ContextKey::fromString('trace.id'), 'abc123'))
    ->with(ContextEntry::restricted(ContextKey::fromString('customer.dob'), '1990-01-01'))
    ->with(ContextEntry::secret(ContextKey::fromString('api.token'), 's3cr3t'));

echo 'context entries (classification drives default edge behavior):' . PHP_EOL;
foreach ($context->toArray() as $entry) {
    echo '  - ' . $entry['key'] . ' => ' . $entry['classification'] . PHP_EOL;
}

echo 'secret values are never exported or serialized; restricted values are masked on redaction.' . PHP_EOL;
