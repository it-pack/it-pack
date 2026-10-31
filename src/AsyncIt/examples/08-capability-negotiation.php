<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use ItPack\AsyncIt\Contract\Port\CapabilityKind;
use ItPack\AsyncIt\Contract\Port\CapabilityStatus;
use ItPack\AsyncIt\Domain\CapabilityDescriptor;

$fixture = makeReferenceRuntime();
$runtime = $fixture['runtime'];
$scope = makeScope('capability-negotiation');

echo "=== CapabilityDescriptor: the negotiated runtime model ===\n";

// The CapabilityDescriptor is the single source of truth for the active
// environment. Each CapabilityKind maps to a CapabilityStatus:
// Supported (native), Emulated (userspace fallback), AdapterOnly (shim), or
// Unavailable (not available). Policy is selected from this descriptor, never
// by probing extension_loaded() or vendor names.

$descriptor = new CapabilityDescriptor([
    CapabilityKind::FiberExecution->value       => CapabilityStatus::Supported,
    CapabilityKind::EdgeTriggeredPolling->value => CapabilityStatus::Emulated,
    CapabilityKind::NativeChannels->value       => CapabilityStatus::Unavailable,
    CapabilityKind::BlockingHook->value         => CapabilityStatus::AdapterOnly,
], version: 3);

echo '  descriptor version: ', $descriptor->version(), "\n";

foreach (CapabilityKind::cases() as $kind) {
    $status = $descriptor->statusFor($kind);
    echo '  ', str_pad($kind->value, 28), ' => ', $status->value, "\n";
}

echo "\n  statusFor(FiberExecution):       ", $descriptor->statusFor(CapabilityKind::FiberExecution)->value, "\n";
echo '  statusFor(NativeChannels):       ', $descriptor->statusFor(CapabilityKind::NativeChannels)->value, "\n";
echo '  statusFor(UnknownKind fallback): ', $descriptor->statusFor(CapabilityKind::BlockingHook)->value, " (default: Unavailable if missing)\n";

echo "\n=== AsyncRuntime::capability() — EngineCapability ===\n";

// capability() returns the RunIt EngineCapability negotiated by the adapter
// assembler. These are boolean flags derived from the CapabilityDescriptor.
$capability = $runtime->capability();

echo '  supportsFibers:     ', $capability->supportsFibers ? 'true' : 'false', "\n";
echo '  supportsCoroutines: ', $capability->supportsCoroutines ? 'true' : 'false', "\n";
echo '  concurrent:         ', $capability->concurrent ? 'true' : 'false', "\n";

echo "\n=== Capability-driven policy selection ===\n";

// Schedulers and adapters select policy from the descriptor. Example: if
// native channels are Unavailable, the runtime falls back to EmulatedChannel
// under the same TaskChannelInterface contract — consumer code does not
// change.
$nativeChannels = $descriptor->statusFor(CapabilityKind::NativeChannels);

if ($nativeChannels === CapabilityStatus::Supported) {
    echo "  channel backend: native (runtime-provided)\n";
} elseif ($nativeChannels === CapabilityStatus::Emulated) {
    echo "  channel backend: emulated (userspace fallback)\n";
} elseif ($nativeChannels === CapabilityStatus::AdapterOnly) {
    echo "  channel backend: adapter shim\n";
} else {
    echo "  channel backend: unavailable (no channel support)\n";
}

$fiberExec = $descriptor->statusFor(CapabilityKind::FiberExecution);
if ($fiberExec === CapabilityStatus::Supported) {
    echo "  scheduling model: native Fiber execution\n";
}

echo "\n  All examples in this directory run on the reference adapter:\n";
echo "  SelectPoller + EmulatedChannel + MemoryContextStorage\n";
