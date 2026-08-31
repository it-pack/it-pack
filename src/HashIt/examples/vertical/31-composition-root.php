<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeContextFactory;
use ItPack\HashIt\Adapter\NativeDirectoryListing;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\HashObservation;
use ItPack\HashIt\HashingObserverInterface;
use ItPack\HashIt\Hasher;

banner('Composition root — the hexagonal wiring lives in one place');

echo "NativeHashIt is the ONLY class that knows the concrete adapters. It binds:\n";
echo "  - NativeContextFactory    (builds hash contexts)\n";
echo "  - NativeDirectoryListing  (lists directory files)\n";
echo "  - HashAlgorithm catalogue (default = native)\n";
echo "  - an optional observer    (observability port)\n";

// You can reproduce the same wiring yourself by constructing the core Hasher
// against the PORT interfaces — proving the core depends only on ports, never
// on the concrete adapters.
final class SilentObserver implements HashingObserverInterface
{
    public function observe(HashObservation $o): void
    {
    }
}

$custom = new Hasher(
    new NativeContextFactory(),
    new NativeDirectoryListing(),
    new SilentObserver(),
);

echo "\nManually composed Hasher (core depends only on ports):\n";
echo '  sha256(\'wired\') = ' . $custom->hash('wired')->value() . "\n";

echo "\nSwap-in points — each is a port you can reimplement:\n";
echo "  ContextFactoryInterface    -> e.g. OpenSSL-backed contexts\n";
echo "  DirectoryListingInterface  -> e.g. cloud-bucket listing\n";
echo "  AlgorithmSourceInterface   -> e.g. KMS-backed identifiers\n";
echo "  HashingObserverInterface   -> e.g. OpenTelemetry, CloudEvents\n";
echo "  HashProgressInterface      -> e.g. async progress notifications\n";

echo "\nAdapters live in ItPack\\HashIt\\Adapter\\*; the core never references them\n";
echo "except through NativeHashIt. That is the ports & adapters boundary.\n";
