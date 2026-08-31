<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use RuntimeException;

/**
 * Verify a {@see HashManifest} against the filesystem by re-hashing each expected
 * path with the supplied hasher and comparing with {@see HashInterface::equals()}
 * (constant time). Logic lives in the core; the text (de)serialization of manifests
 * is deliberately out of scope here and lives in the walled {@see \ItPack\HashIt\Format}
 * namespace (roadmap guardrail #7).
 */
final class ManifestVerifier
{
    public function verify(HashManifest $expected, HasherInterface $hasher): VerificationReport
    {
        $matched = [];
        $mismatched = [];
        $missing = [];

        foreach ($expected->entries() as $path => $expectedHash) {
            try {
                $actual = $hasher->hashFile(new Filepath($path));
            } catch (RuntimeException $e) {
                $missing[] = $path;
                continue;
            }

            if ($actual->equals($expectedHash)) {
                $matched[$path] = $actual;
            } else {
                $mismatched[] = new HashMismatch($path, $expectedHash, $actual);
            }
        }

        return new VerificationReport($matched, $mismatched, $missing);
    }
}
