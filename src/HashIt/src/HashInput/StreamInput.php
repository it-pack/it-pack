<?php

declare(strict_types=1);

namespace ItPack\HashIt\HashInput;

use InvalidArgumentException;
use ItPack\HashIt\HasherInterface;
use ItPack\HashIt\HashInputInterface;
use ItPack\HashIt\HashSinkInterface;

final class StreamInput implements HashInputInterface
{
    /**
     * @param resource $stream
     */
    public function __construct(
        private $stream,
        private int $chunkSize = HasherInterface::DEFAULT_CHUNK_SIZE_BYTES,
    ) {
    }

    public function writeTo(HashSinkInterface $sink): HashSinkInterface
    {
        if (!is_resource($this->stream) || get_resource_type($this->stream) !== 'stream') {
            throw new InvalidArgumentException('StreamInput expects a stream resource');
        }

        if ($this->chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        $position = ftell($this->stream);
        if ($position === false) {
            $position = 0;
        }
        fseek($this->stream, 0);

        $current = $sink;

        try {
            while (!feof($this->stream)) {
                $chunk = fread($this->stream, $this->chunkSize);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $current = $current->append($chunk);
            }
        } finally {
            fseek($this->stream, $position);
        }

        return $current;
    }
}
