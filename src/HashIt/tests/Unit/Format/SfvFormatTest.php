<?php

declare(strict_types=1);

namespace ItPack\HashIt\Test\Unit\Format;

use ItPack\HashIt\Contract\ManifestFormatInterface;
use ItPack\HashIt\Format\SfvFormat;
use ItPack\HashIt\Hash;
use ItPack\HashIt\HashAlgorithm;
use ItPack\HashIt\HashManifest;
use PHPUnit\Framework\TestCase;

use function verify;

final class SfvFormatTest extends TestCase
{
    private function format(): ManifestFormatInterface
    {
        return new SfvFormat();
    }
    public function testRoundTripSha256(): void
    {
        $manifest = new HashManifest(HashAlgorithm::SHA256)
            ->with('a.txt', Hash::create(hash('sha256', 'alpha'), 'sha256'))
            ->with('b.txt', Hash::create(hash('sha256', 'beta'), 'sha256'))
        ;

        $text = new SfvFormat()->serialize($manifest);
        $parsed = new SfvFormat()->parse($text);

        verify($parsed->algorithm())->equals($manifest->algorithm());
        verify($parsed->entries())->equals($manifest->entries());
    }

    public function testHeaderCarriesAlgorithm(): void
    {
        $manifest = new HashManifest(HashAlgorithm::SHA512_256)
            ->with('a.txt', Hash::create(hash('sha512/256', 'alpha'), 'sha512/256'))
        ;

        $text = new SfvFormat()->serialize($manifest);

        self::assertStringContainsString('# algorithm sha512/256', $text);

        $parsed = new SfvFormat()->parse($text);
        verify($parsed->algorithm())->equals(HashAlgorithm::SHA512_256);
        verify($parsed->entries())->equals($manifest->entries());
    }

    public function testDefaultsToSha256WithoutHeader(): void
    {
        $text = 'a.txt ' . hash('sha256', 'alpha') . "\n";

        $parsed = $this->format()->parse($text);
        verify($parsed->algorithm())->equals(HashAlgorithm::SHA256);
        verify($parsed->entries())->equals(
            new HashManifest(HashAlgorithm::SHA256)
                ->with('a.txt', Hash::create(hash('sha256', 'alpha'), 'sha256'))
                ->entries(),
        );
    }

    public function testRejectsMalformedLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->format()->parse("# algorithm sha256\nbad line here\n");
    }
}
