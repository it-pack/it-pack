<?php

declare(strict_types=1);

namespace ItPack\HashIt;

use ItPack\HashIt\Contract\HashInterface;
use ItPack\HashIt\Contract\URNBuilderInterface;
use RuntimeException;

final readonly class URNBuilder implements URNBuilderInterface
{
    private const string SCHEME = 'urn';
    private const string NAMESPACE_IDENTIFIER = 'hashit';
    public ?string $algorithm;

    public function __construct(
        public ?string $hex = null,
        HashAlgorithm|string $algorithm = HashAlgorithm::SHA3_256,
    ) {
        $this->algorithm = $algorithm instanceof HashAlgorithm
            ? $algorithm->value
            : $algorithm;
    }

    public static function fromHash(HashInterface $hash): self
    {
        return new self(
            $hash->binary() ? bin2hex($hash->value()) : $hash->value(),
            $hash->algorithm(),
        );
    }

    public function withHash(HashInterface $hash): self
    {
        return self::fromHash($hash);
    }

    public function withHex(string $hex): self
    {
        return new self($hex, $this->algorithm);
    }

    public function withAlgorithm(HashAlgorithm|string $algorithm): self
    {
        return new self($this->hex, $algorithm);
    }

    public function build(): string
    {
        if ($this->hex === null) {
            throw new RuntimeException(
                'Hex value ust be set'
            );
        }

        return sprintf(
            '%s:%s:%s:%s',
            self::SCHEME,
            self::NAMESPACE_IDENTIFIER,
            $this->algorithm ?? HashAlgorithm::default()->value,
            $this->hex,
        );
    }
}
