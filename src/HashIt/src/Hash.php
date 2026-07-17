<?php

declare(strict_types=1);

namespace ItPack\HashIt\src;

class Hash implements HashInterface
{
    public function __construct(){}

    public string $algorithm {
        get {
            return $this->algorithm;
        }
    }
    public bool $binary {
        get {
            return $this->binary;
        }
    }
    public string $value {
        get {
            return $this->value;
        }
    }

    public int $length {
        get {
            return $this->length ?: strlen($this->value);
        }
    }

    public static function create(): self
    {
        return new self();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}