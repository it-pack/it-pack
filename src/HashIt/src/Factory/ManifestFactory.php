<?php

declare(strict_types=1);

namespace ItPack\HashIt\Factory;

use ItPack\HashIt\Contract\HashInterface;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;
use ItPack\HashIt\HashMismatch;
use ItPack\HashIt\VerificationReport;

/**
 * The single place in the package that may instantiate the manifest-domain
 * value objects ({@see HashManifest}, {@see HashMismatch},
 * {@see VerificationReport}). Hashing internals and the walled {@see Format}
 * formatters inject this port and call {@see self::createManifest()} etc.
 * instead of `new HashManifest(…)` etc., keeping the value-object creation
 * surface auditable.
 *
 * Layer: Core.
 */
final class ManifestFactory
{
    /**
     * @param array<string, HashInterface> $entries
     */
    public function createManifest(
        HashAlgorithm $algorithm,
        array $entries = [],
    ): HashManifest {
        return new HashManifest($algorithm, $entries);
    }

    public function createMismatch(
        string $path,
        HashInterface $expected,
        HashInterface $actual,
    ): HashMismatch {
        return new HashMismatch($path, $expected, $actual);
    }

    /**
     * @param array<string, HashInterface> $matched
     * @param list<HashMismatch> $mismatched
     * @param list<string> $missing
     */
    public function createReport(
        array $matched,
        array $mismatched,
        array $missing,
    ): VerificationReport {
        return new VerificationReport($matched, $mismatched, $missing);
    }
}
