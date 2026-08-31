<?php

declare(strict_types=1);

namespace ItPack\HashIt\Test\Unit;

use ItPack\HashIt\Adapter\NativeFilepathFileinfo;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\Contract\HasherInterface;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;
use ItPack\HashIt\ManifestVerifier;
use PHPUnit\Framework\TestCase;

final class ManifestVerifierTest extends TestCase
{
    private function hasher(): HasherInterface
    {
        return NativeHashIt::create();
    }

    private function verifier(): ManifestVerifier
    {
        return new ManifestVerifier();
    }

    public function testMatched(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'payload');

        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with($tmp, $this->hasher()->hashFile(NativeFilepathFileinfo::of($tmp)))
        ;

        $report = $this->verifier()->verify($manifest, $this->hasher());

        self::assertTrue($report->allMatched());
        self::assertArrayHasKey($tmp, $report->matched());
        self::assertEmpty($report->mismatched());
        self::assertEmpty($report->missing());

        unlink($tmp);
    }

    public function testMismatched(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($tmp, 'original');

        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with($tmp, $this->hasher()->hashFile(NativeFilepathFileinfo::of($tmp)))
        ;

        file_put_contents($tmp, 'tampered');

        $report = $this->verifier()->verify($manifest, $this->hasher());

        self::assertFalse($report->allMatched());
        self::assertCount(1, $report->mismatched());
        $mismatch = $report->mismatched()[0];
        self::assertSame($tmp, $mismatch->path);
        self::assertNotSame($mismatch->expected->value(), $mismatch->actual->value());
        self::assertEmpty($report->missing());

        unlink($tmp);
    }

    public function testMissing(): void
    {
        $missing = sys_get_temp_dir() . '/hashit-does-not-exist-' . uniqid() . '.bin';

        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with($missing, $this->hasher()->hash('whatever'))
        ;

        $report = $this->verifier()->verify($manifest, $this->hasher());

        self::assertFalse($report->allMatched());
        self::assertSame([$missing], $report->missing());
        self::assertEmpty($report->matched());
    }

    public function testMixedReport(): void
    {
        $good = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($good, 'good');
        $bad = tempnam(sys_get_temp_dir(), 'hashit');
        file_put_contents($bad, 'bad');
        $gone = sys_get_temp_dir() . '/hashit-gone-' . uniqid() . '.bin';

        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with($good, $this->hasher()->hashFile(NativeFilepathFileinfo::of($good)))
            ->with($bad, $this->hasher()->hashFile(NativeFilepathFileinfo::of($bad)))
            ->with($gone, $this->hasher()->hash('bad'))
        ;

        file_put_contents($bad, 'tampered');

        $report = $this->verifier()->verify($manifest, $this->hasher());

        self::assertArrayHasKey($good, $report->matched());
        self::assertCount(1, $report->mismatched());
        self::assertSame($bad, $report->mismatched()[0]->path);
        self::assertSame([$gone], $report->missing());

        unlink($good);
        unlink($bad);
    }

    public function testThrowOnFailure(): void
    {
        $missing = sys_get_temp_dir() . '/hashit-gone-' . uniqid() . '.bin';
        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with($missing, $this->hasher()->hash('x'))
        ;

        $report = $this->verifier()->verify($manifest, $this->hasher());

        $this->expectException(\RuntimeException::class);
        $report->throwOnFailure();
    }
}
