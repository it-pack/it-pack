<?php

declare(strict_types=1);

namespace ItPack\HashIt\Test\Unit\Format;

use ItPack\HashIt\Contract\ManifestFormatInterface;
use ItPack\HashIt\Format\GnuSha256Format;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;
use PHPUnit\Framework\TestCase;

use function verify;

final class GnuSha256FormatTest extends TestCase
{
    private function format(): ManifestFormatInterface
    {
        return new GnuSha256Format();
    }
    public function testRoundTrip(): void
    {
        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with('a.txt', Hash::create(hash('sha256', 'alpha'), 'sha256'))
            ->with('b c.txt', Hash::create(hash('sha256', 'beta gamma'), 'sha256'))
        ;

        $text = $this->format()->serialize($manifest);
        $parsed = $this->format()->parse($text);

        verify($parsed->algorithm())->equals($manifest->algorithm());
        verify($parsed->entries())->equals($manifest->entries());
    }

    public function testMatchesSha256sumFixture(): void
    {
        $content = 'the quick brown fox';
        $hex = hash('sha256', $content);

        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with('fox.txt', Hash::create($hex, 'sha256'))
        ;

        verify($this->format()->serialize($manifest))
            ->equals($hex . '  fox.txt' . "\n")
        ;

        // GNU sha256sum emits a binary-mode line with a leading space+asterisk too.
        $parsed = $this->format()->parse($hex . ' *fox.txt' . "\n");
        verify($parsed->entries()['fox.txt']->value())->equals($hex);
    }

    public function testRejectsNonSha256Manifest(): void
    {
        $manifest = new HashManifest(HashAlgorithm::SHA512)
            ->with('x', Hash::create(hash('sha512', 'x'), 'sha512'))
        ;

        $this->expectException(\InvalidArgumentException::class);
        $this->format()->serialize($manifest);
    }

    public function testRejectsMalformedLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->format()->parse("not-a-valid-line\n");
    }

    public function testParseSkipsBlankLines(): void
    {
        $hexA = hash('sha256', 'alpha');
        $hexB = hash('sha256', 'beta');

        $text = "{$hexA}  a.txt\n\n{$hexB}  b.txt\n";

        $parsed = $this->format()->parse($text);

        verify($parsed->entries())->equals(
            new HashManifest(HashAlgorithm::SHA256)
                ->with('a.txt', Hash::create($hexA, 'sha256'))
                ->with('b.txt', Hash::create($hexB, 'sha256'))
                ->entries(),
        );
    }
}
