<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Service;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Service\DefaultSecretScanner;
use ItPack\CommitIt\Domain\Value\SecretType;

/**
 * @covers \ItPack\CommitIt\Domain\Service\DefaultSecretScanner
 * @covers \ItPack\CommitIt\Domain\Value\SecretType
 */
final class DefaultSecretScannerTest extends Unit
{
    public function testDetectsGitHubToken(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan('Add feature ghp_1234567890123456789012345678901234567');

        self::assertCount(1, $matches);
        self::assertSame(SecretType::GitHubToken, $matches[0]->type);
    }

    public function testDetectsPrivateKey(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan("BEGIN PRIVATE KEY\n-----BEGIN RSA PRIVATE KEY-----");

        self::assertCount(1, $matches);
        self::assertSame(SecretType::PrivateKey, $matches[0]->type);
    }

    public function testDetectsBearerToken(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan('Authorization: Bearer abc123def456');

        self::assertCount(1, $matches);
        self::assertSame(SecretType::BearerToken, $matches[0]->type);
    }

    public function testDetectsGenericApiKey(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan('api_key=sk-1234567890123456789012');

        self::assertCount(1, $matches);
        self::assertSame(SecretType::GenericApiKey, $matches[0]->type);
    }

    public function testNoFalsePositiveOnCleanContent(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan('Fix bug in user authentication flow');

        self::assertCount(0, $matches);
    }

    public function testMultipleSecretsInSingleContent(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan('ghp_token_1234567890123456789012345678901234567 and Bearer abc123');

        self::assertCount(2, $matches);
        self::assertSame(SecretType::GitHubToken, $matches[0]->type);
        self::assertSame(SecretType::BearerToken, $matches[1]->type);
    }

    public function testMatchesAreSortedByOffset(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan('Bearer abc123 then ghp_1234567890123456789012345678901234567');

        self::assertCount(2, $matches);
        self::assertLessThan($matches[1]->offset, $matches[0]->offset);
    }

    public function testEmptyContentReturnsNoMatches(): void
    {
        $scanner = new DefaultSecretScanner();
        $matches = $scanner->scan('');

        self::assertCount(0, $matches);
    }

    public function testMatchContainsCorrectOffsetAndLength(): void
    {
        $scanner = new DefaultSecretScanner();
        $content = 'prefix ghp_1234567890123456789012345678901234567 suffix';
        $matches = $scanner->scan($content);

        self::assertCount(1, $matches);
        self::assertSame(7, $matches[0]->offset);
        self::assertSame(41, $matches[0]->length);
    }
}
