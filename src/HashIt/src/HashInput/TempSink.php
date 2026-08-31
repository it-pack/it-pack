<?php

declare(strict_types=1);

namespace ItPack\HashIt\HashInput;

use InvalidArgumentException;
use ItPack\HashIt\Contract\HashSinkInterface;
use RuntimeException;

/**
 * A measuring, rewindable {@see HashSinkInterface} backed by an in-memory /
 * spill-to-temp stream. Used by {@see CompositeInput} to frame each child: it
 * captures the child's framed bytes, reports their length, then yields them back
 * in chunks so the length prefix can be written *before* the bytes — without the
 * child's source stream being loaded fully into memory at once.
 */
final class TempSink implements HashSinkInterface
{
    /** @var resource */
    private $handle;
    private int $written = 0;

    public function __construct()
    {
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary stream');
        }
        $this->handle = $handle;
    }

    public function append(string $data): HashSinkInterface
    {
        fwrite($this->handle, $data);
        $this->written += strlen($data);

        return $this;
    }

    public function length(): int
    {
        return $this->written;
    }

    /**
     * @return iterable<string>
     */
    public function chunks(int $size): iterable
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        rewind($this->handle);

        while (!feof($this->handle)) {
            $chunk = fread($this->handle, $size);
            if ($chunk === false || $chunk === '') {
                break;
            }
            yield $chunk;
        }
    }
}
