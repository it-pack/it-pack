<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Service;

use ItPack\CommitIt\Contract\SecretScanner;
use ItPack\CommitIt\Domain\Option\SecurityScanOptions;
use ItPack\CommitIt\Domain\Value\SecretMatch;
use ItPack\CommitIt\Domain\Value\SecretType;
use SensitiveParameter;

/**
 * Pattern-based {@see SecretScanner} implementation.
 *
 * Scans content using well-known regex patterns for common credential types.
 * Matched secrets are never returned to callers — only {@see SecretMatch}
 * metadata (type, offset, length, description) is exposed.
 *
 * @api
 */
final readonly class DefaultSecretScanner implements SecretScanner
{
    /** @var list<array{type: SecretType, pattern: string, description: string}> */
    private array $patterns;

    private SecurityScanOptions $options;

    public function __construct(
        ?SecurityScanOptions $options = null,
    ) {
        $this->options = $options ?? SecurityScanOptions::default();

        $this->patterns = [
            ['type' => SecretType::AwsAccessKey, 'pattern' => '/\bAKIA[0-9A-Z]{16}\b/', 'description' => 'AWS Access Key ID'],
            ['type' => SecretType::AwsSecretKey, 'pattern' => '~^[0-9a-zA-Z/_\-]{40}$~', 'description' => 'AWS Secret Access Key'],
            ['type' => SecretType::GitHubToken, 'pattern' => '/\bgh[pousr]_[A-Za-z0-9_]{36,}\b/', 'description' => 'GitHub token'],
            ['type' => SecretType::SlackToken, 'pattern' => '/\b(xox[baprs]-[A-Za-z0-9-]+)\b/', 'description' => 'Slack token'],
            ['type' => SecretType::PrivateKey, 'pattern' => '/-----BEGIN [A-Z ]+PRIVATE KEY-----/', 'description' => 'Private key block'],
            ['type' => SecretType::BearerToken, 'pattern' => '/\bBearer\s+[A-Za-z0-9_\-.=]+\b/i', 'description' => 'Bearer token'],
            ['type' => SecretType::BasicAuth, 'pattern' => '/\bBasic\s+[A-Za-z0-9_\-+\/]{10,}={0,2}\b/i', 'description' => 'HTTP Basic Auth'],
            ['type' => SecretType::ConnectionString, 'pattern' => '/\b(amqp|postgres|mysql|mongodb|redis):\/\/[^\s]+:[^\s]+@[^\s]+\b/i', 'description' => 'Database connection string'],
            ['type' => SecretType::GenericApiKey, 'pattern' => '/\b(api[_-]?key|apikey|token|secret|password|passwd|pwd)\s*[:=]\s*[A-Za-z0-9_\/\-]{8,}\b/i', 'description' => 'Generic API key or secret'],
            ['type' => SecretType::GenericPassword, 'pattern' => '/\bpass(word)?\s*[:=]\s*[^\s]+/i', 'description' => 'Generic password field'],
        ];
    }

    public function scan(#[SensitiveParameter] string $content): array
    {
        $matches = [];

        foreach ($this->patterns as $patternDef) {
            $offset = 0;

            while (preg_match($patternDef['pattern'], $content, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $position = (int)$m[0][1];
                $matchedString = $m[0][0];
                $length = strlen($matchedString);

                if ($this->options->maxMatchLength !== null && $length > $this->options->maxMatchLength) {
                    $offset = $position + 1;
                    continue;
                }

                $matches[] = new SecretMatch(
                    $patternDef['type'],
                    $position,
                    $length,
                    $patternDef['description'],
                );

                $offset = $position + 1;
            }
        }

        usort($matches, static fn (SecretMatch $a, SecretMatch $b): int => $a->offset <=> $b->offset);

        return $matches;
    }
}
