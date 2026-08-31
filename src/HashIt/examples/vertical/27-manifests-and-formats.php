<?php

declare(strict_types=1);

require_once __DIR__ . '/../_support.php';

use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Filepath;
use ItPack\HashIt\Format\BsdFormat;
use ItPack\HashIt\Format\GnuSha256Format;
use ItPack\HashIt\Format\SfvFormat;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;
use ItPack\HashIt\ManifestVerifier;

banner('Manifests & verification formats (BSD / GNU-sha256sum / SFV)');

$hasher = NativeHashIt::create();
$tmp = makeTempDir();

// Build a manifest of expected hashes keyed by absolute path.
$manifest = new HashManifest(HashAlgorithm::SHA256);
foreach ($tmp['files'] as $path) {
    $manifest = $manifest->with($path, $hasher->hashFile(new Filepath($path)));
}

// Serialise to each supported text format.
$bsd = (new BsdFormat())->serialize($manifest);
$gnu = (new GnuSha256Format())->serialize($manifest);
$sfv = (new SfvFormat())->serialize($manifest);

echo "BSD format:\n" . $bsd . "\n";
echo "GNU sha256sum format:\n" . $gnu . "\n";
echo "SFV format:\n" . $sfv . "\n";

// Round-trip: parse a format back and verify it against the filesystem.
$verifier = new ManifestVerifier();

$report = $verifier->verify((new BsdFormat())->parse($bsd), $hasher);
echo 'verify BSD manifest    -> '
    . ($report->allMatched() ? 'ALL MATCHED' : 'FAIL')
    . ' (' . count($report->matched()) . " files)\n";

// Tamper with a file: re-verify -> a mismatch shows up.
file_put_contents($tmp['files'][0], 'TAMPERED');
$report2 = $verifier->verify((new GnuSha256Format())->parse($gnu), $hasher);
echo 'after tamper           -> matched: ' . count($report2->matched())
    . ', mismatched: ' . count($report2->mismatched())
    . ', missing: ' . count($report2->missing()) . "\n";

// Delete a file: re-verify -> it is reported as missing.
unlink($tmp['files'][1]);
$report3 = $verifier->verify((new SfvFormat())->parse($sfv), $hasher);
echo 'after delete           -> matched: ' . count($report3->matched())
    . ', mismatched: ' . count($report3->mismatched())
    . ', missing: ' . count($report3->missing()) . "\n";

echo "\nUse VerificationReport::throwOnFailure() to fail closed in one call.\n";
