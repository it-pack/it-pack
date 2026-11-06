<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;

/**
 * Redactor that masks sensitive values in an artifact's normalized projection.
 *
 * It operates on the artifact's `normalize()` output so it never mutates the
 * original: when something must be masked it returns a {@see RedactedArtifact}
 * with the masked projection; when nothing matches it returns the same artifact.
 *
 * Masking covers:
 *
 *  - attribute values whose key is in `sensitiveAttributeKeys` (the classified
 *    context surface carried by every artifact);
 *  - throwable messages, when enabled;
 *  - occurrence `data`, recursively, when enabled;
 *  - snapshot `payload`, recursively, when enabled.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DefaultArtifactRedactor implements RedactorInterface
{
    public function __construct(
        private ArtifactRedactionConfig $config = new ArtifactRedactionConfig(),
    ) {
    }

    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        $normalized = $artifact->normalize();

        $changed = false;

        if ($this->config->sensitiveAttributeKeys !== []
            && isset($normalized[ArtifactField::ATTRIBUTES])
            && is_array($normalized[ArtifactField::ATTRIBUTES])
        ) {
            [$normalized[ArtifactField::ATTRIBUTES], $masked] = $this->maskKeys(
                $normalized[ArtifactField::ATTRIBUTES],
                $this->config->sensitiveAttributeKeys
            );
            if ($masked) {
                $changed = true;
            }
        }

        if ($this->config->redactThrowableMessages
            && isset($normalized[ArtifactField::THROWABLE])
            && is_array($normalized[ArtifactField::THROWABLE])
        ) {
            [$normalized[ArtifactField::THROWABLE], $masked] = $this->maskThrowable(
                $normalized[ArtifactField::THROWABLE],
            );
            if ($masked) {
                $changed = true;
            }
        }

        if ($this->config->redactOccurrenceData
            && $artifact instanceof OccurrenceInterface
            && isset($normalized[ArtifactField::DATA])
        ) {
            [$normalized[ArtifactField::DATA], $masked] = $this->maskKeysRecursive(
                $normalized[ArtifactField::DATA],
                $this->config->sensitiveDataKeys
            );
            if ($masked) {
                $changed = true;
            }
        }

        if ($this->config->redactSnapshotPayloads
            && $artifact instanceof SnapshotInterface
            && isset($normalized[ArtifactField::PAYLOAD])
        ) {
            [$normalized[ArtifactField::PAYLOAD], $masked] = $this->maskKeysRecursive(
                $normalized[ArtifactField::PAYLOAD],
                $this->config->sensitiveDataKeys
            );
            if ($masked) {
                $changed = true;
            }
        }

        if (!$changed) {
            return $artifact;
        }

        return new RedactedArtifact($normalized);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $keys
     * @return array{0: array<array-key, mixed>, 1: bool}
     */
    private function maskKeys(array $data, array $keys): array
    {
        $masked = false;
        $keySet = array_fill_keys($keys, true);

        foreach ($data as $key => $value) {
            if (isset($keySet[$key])) {
                $data[$key] = $this->config->marker;
                $masked = true;
            }
        }

        return [$data, $masked];
    }

    /**
     * @param  mixed       $data
     * @param  list<string> $keys
     * @return array{0: mixed, 1: bool}
     */
    private function maskKeysRecursive(mixed $data, array $keys): array
    {
        if (!is_array($data) || $keys === []) {
            return [$data, false];
        }

        $masked = false;
        $keySet = array_fill_keys($keys, true);

        foreach ($data as $key => $value) {
            if (isset($keySet[$key])) {
                $data[$key] = $this->config->marker;
                $masked = true;

                continue;
            }

            if (is_array($value)) {
                [$data[$key], $innerMasked] = $this->maskKeysRecursive($value, $keys);
                if ($innerMasked) {
                    $masked = true;
                }
            }
        }

        return [$data, $masked];
    }

    /**
     * @param array<array-key, mixed> $throwable
     * @return array{0: array<array-key, mixed>, 1: bool}
     */
    private function maskThrowable(array $throwable): array
    {
        $masked = false;

        if (array_key_exists(ArtifactField::MESSAGE, $throwable) && is_string($throwable[ArtifactField::MESSAGE])) {
            $throwable[ArtifactField::MESSAGE] = $this->config->marker;
            $masked = true;
        }

        if (array_key_exists(ArtifactField::TRACE, $throwable) && is_string($throwable[ArtifactField::TRACE])) {
            $throwable[ArtifactField::TRACE] = $this->config->marker;
            $masked = true;
        }

        if (array_key_exists(ArtifactField::PREVIOUS, $throwable) && is_array($throwable[ArtifactField::PREVIOUS])) {
            [$throwable[ArtifactField::PREVIOUS], $innerMasked] = $this->maskThrowable($throwable[ArtifactField::PREVIOUS]);
            if ($innerMasked) {
                $masked = true;
            }
        }

        return [$throwable, $masked];
    }
}
