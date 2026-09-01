<?php

declare(strict_types=1);

namespace ItPack\HashIt\Test\Unit\Format;

use ItPack\HashIt\Contract\ManifestFormatInterface;
use ItPack\HashIt\Factory\HashFactory;
use ItPack\HashIt\Factory\ManifestFactory;
use ItPack\HashIt\Format\BsdFormat;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;
use PHPUnit\Framework\TestCase;

use function verify;

final class BsdFormatTest extends TestCase
{
    private function format(): ManifestFormatInterface
    {
        return new BsdFormat(new HashFactory(), new ManifestFactory());
    }
    public function testRoundTripSha256(): void
    {
        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with('a.txt', Hash::create(hash('sha256', 'alpha'), 'sha256'))
            ->with('b.txt', Hash::create(hash('sha256', 'beta'), 'sha256'))
        ;

        $text = $this->format()->serialize($manifest);
        $parsed = $this->format()->parse($text);

        verify($parsed->algorithm())->equals($manifest->algorithm());
        verify($parsed->entries())->equals($manifest->entries());
    }

    public function testRoundTripSha512(): void
    {
        $manifest = new HashManifest(HashAlgorithm::SHA512)
            ->with('a.txt', Hash::create(hash('sha512', 'alpha'), 'sha512'))
        ;

        $text = $this->format()->serialize($manifest);
        $parsed = $this->format()->parse($text);

        verify($parsed->algorithm())->equals(HashAlgorithm::SHA512);
        verify($parsed->entries())->equals($manifest->entries());
    }

    public function testMatchesBsdFixture(): void
    {
        $hex = hash('sha256', 'fox');
        $expected = 'SHA256 (fox.txt) = ' . $hex . "\n";

        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with('fox.txt', Hash::create($hex, 'sha256'))
        ;

        verify($this->format()->serialize($manifest))->equals($expected);
    }

    public function testRejectsMixedAlgorithms(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->format()->parse(
            'SHA256 (a) = ' . hash('sha256', 'a') . "\n"
            . 'SHA512 (b) = ' . hash('sha512', 'b') . "\n",
        );
    }

    public function testRejectsUnknownAlgorithm(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->format()->parse('BOGUS (a) = deadbeef' . "\n");
    }

    public function testRejectsMalformedLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->format()->parse("not a bsd line\n");
    }

    public function testParseEmptyTextThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->format()->parse('');
    }

    public function testParseSkipsBlankLines(): void
    {
        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with('a.txt', Hash::create(hash('sha256', 'alpha'), 'sha256'))
            ->with('b.txt', Hash::create(hash('sha256', 'beta'), 'sha256'))
        ;

        $text = $this->format()->serialize($manifest);
        $withBlank = 'SHA256 (a.txt) = ' . hash('sha256', 'alpha')
            . "\n\nSHA256 (b.txt) = " . hash('sha256', 'beta') . "\n";

        $parsed = $this->format()->parse($withBlank);

        verify($parsed->entries())->equals($manifest->entries());
    }
}
