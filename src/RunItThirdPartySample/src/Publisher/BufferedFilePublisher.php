<?php

declare(strict_types=1);

namespace ItPack\RunItThirdPartySample\Publisher;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use RuntimeException;

/**
 * Buffered file publisher: it accumulates normalized artifacts and emits them only
 * on {@see self::flush()} or {@see self::close()}, satisfying the optional
 * {@see FlushablePublisherInterface} and {@see CloseablePublisherInterface} supplements.
 *
 * The shipped {@see \ItPack\RunIt\Conformance\Verifier\ArtifactPublisherVerifier} exercises
 * the warning-severity branches for flush/close: a conformant buffered publisher
 * must flush and close repeatedly without throwing.
 */
final class BufferedFilePublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    /** @var resource|null */
    private $handle = null;

    public function __construct(
        private readonly string $path,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->buffer[] = $artifact->normalize();
    }

    public function flush(): void
    {
        if ($this->handle === null) {
            $handle = fopen($this->path, 'a');

            if ($handle === false) {
                throw new RuntimeException(sprintf('Unable to open "%s" for writing.', $this->path));
            }

            $this->handle = $handle;
        }

        foreach ($this->buffer as $item) {
            fwrite($this->handle, (string) json_encode($item) . PHP_EOL);
        }

        $this->buffer = [];
    }

    public function close(): void
    {
        $this->flush();

        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
