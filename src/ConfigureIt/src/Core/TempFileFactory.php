<?php

declare(strict_types=1);

namespace ItPack\ConfigureIt\Core;

use ItPack\ConfigureIt\Behavior\CreatableBehavior;
use ItPack\ConfigureIt\Contract\TempFileFactoryInterface;
use ItPack\ConfigureIt\Exception\ParseException;

class TempFileFactory implements TempFileFactoryInterface
{

    use CreatableBehavior;
    public const string DEFAULT_TEMP_FILE_PREFIX = 'tmp-file-prefix-it-pack-';

    public function __construct(
        public readonly string $tempFilePrefix = self::DEFAULT_TEMP_FILE_PREFIX,
    ){}

    public static function create(
        ?string $tempFilePrefix = null,
    ): self {
        return new self($tempFilePrefix ?? self::DEFAULT_TEMP_FILE_PREFIX);
    }

    public function build(): ?string
    {
        $temp = tempnam(sys_get_temp_dir(), $this->tempFilePrefix);

        return $temp === false ? null : $temp;
    }

    public function require(): string
    {
        if (($temp = $this->build()) === null) {
            throw new ParseException('Unable to create a temporary file for PHP configuration.');
        }

        return $temp;
    }
}