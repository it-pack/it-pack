<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use ItPack\HashIt\Factory\FilepathFactory;
use ItPack\HashIt\Factory\ManifestFactory;
use RuntimeException;

/**
 * Verify a {@see HashManifest} against the filesystem by re-hashing each expected
 * path with the supplied hasher and comparing with {@see Contract\HashInterface::equals()}
 * (constant time). Logic lives in the core; the text (de)serialization of manifests
 * is deliberately out of scope here and lives in the walled {@see Format}
 * namespace (roadmap guardrail #7).
 */
final class ManifestVerifier
{
    public function __construct(
        private FilepathFactory $filepathFactory,
        private ManifestFactory $manifestFactory,
    ) {
    }

    public function verify(HashManifest $expected, Contract\HasherInterface $hasher): VerificationReport
    {
        $matched = [];
        $mismatched = [];
        $missing = [];

        foreach ($expected->entries() as $path => $expectedHash) {
            try {
                $actual = $hasher->hashFile($this->filepathFactory->create($path));
            } catch (RuntimeException $e) {
                $missing[] = $path;
                continue;
            }

            if ($actual->equals($expectedHash)) {
                $matched[$path] = $actual;
                continue;
            }
            $mismatched[] = $this->manifestFactory->createMismatch($path, $expectedHash, $actual);
        }

        return $this->manifestFactory->createReport($matched, $mismatched, $missing);
    }
}
