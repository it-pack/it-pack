<?php

declare(strict_types=1);

namespace ItPack\HashIt\HashInput;

use ItPack\HashIt\Contract\HashInputInterface;
use ItPack\HashIt\Contract\HashSinkInterface;

final readonly class StringInput implements HashInputInterface
{
    public function __construct(private string $value)
    {
    }

    public function writeTo(HashSinkInterface $sink): HashSinkInterface
    {
        return $sink->append($this->value);
    }
}
