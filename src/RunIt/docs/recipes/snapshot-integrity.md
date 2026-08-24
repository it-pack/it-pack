# Recipe: Snapshot Integrity and Authenticity

Snapshots are immutable, but storage is not necessarily trustworthy. RunIt
separates the two guarantees you usually need when a snapshot comes back from a
store:

- **Integrity** — "this payload is exactly what was hashed at creation" — via
  `SnapshotIntegrityVerifier`.
- **Authenticity** — "this hash was produced by someone holding the key" — via
  a `SnapshotSignerInterface` implementation such as `HmacSnapshotSigner`.

Both are opt-in consumer tooling: nothing in the runner or builder wires them
automatically, because whether stored snapshots need verification depends on
the trust boundary of your storage.

## Verify integrity of a stored snapshot

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Application\Security\SnapshotIntegrityVerifier;
use ItPack\RunIt\Domain\Shared\DeterministicHashCalculator;

$verifier = new SnapshotIntegrityVerifier(new DeterministicHashCalculator());

// Throws SnapshotIntegrityException on tampering.
$verifier->verifyIntegrity($snapshotFromStore);
```

The verifier recomputes the content hash from the snapshot's canonical payload
(the same JSON normalization used at creation) and compares it with
`hash_equals`, so any mutation of payload or hash is rejected.

## Sign snapshots for authenticity

```php
<?php

declare(strict_types=1);

use ItPack\RunIt\Adapter\Common\HmacSnapshotSigner;

$signer = new HmacSnapshotSigner(getenv('SNAPSHOT_SIGNING_KEY'));

$content = (string) json_encode(
    $snapshot->payload(),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
);
$signature = $signer->sign($content);

// Later, at the trust boundary:
if (!$signer->verify($content, $signature)) {
    throw new RuntimeException('Snapshot signature mismatch.');
}
```

Keep the signing key out of artifacts and context; it is a deployment secret,
not telemetry.

## Chain verification

Snapshots that reference a predecessor (`predecessor_id`) can additionally be
checked for ordering and existence:

```php
<?php

declare(strict_types=1);

$verifier->verifyChain($second, $first);
```

`verifyChain()` throws `SnapshotIntegrityException` when the predecessor is
missing or does not precede the successor in time.
