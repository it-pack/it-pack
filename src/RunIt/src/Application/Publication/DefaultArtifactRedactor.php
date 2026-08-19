<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;

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
final class DefaultArtifactRedactor implements RedactorInterface
{
    public function __construct(
        private readonly ArtifactRedactionConfig $config = new ArtifactRedactionConfig(),
    ) {
    }

    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        $normalized = $artifact->normalize();

        $changed = false;

        if ($this->config->sensitiveAttributeKeys !== [] && isset($normalized['attributes']) && is_array($normalized['attributes'])) {
            [$normalized['attributes'], $masked] = $this->maskKeys($normalized['attributes'], $this->config->sensitiveAttributeKeys);
            if ($masked) {
                $changed = true;
            }
        }

        if ($this->config->redactThrowableMessages && isset($normalized['throwable']) && is_array($normalized['throwable'])) {
            [$normalized['throwable'], $masked] = $this->maskThrowable($normalized['throwable']);
            if ($masked) {
                $changed = true;
            }
        }

        if ($this->config->redactOccurrenceData && $artifact instanceof OccurrenceInterface && isset($normalized['data'])) {
            [$normalized['data'], $masked] = $this->maskKeysRecursive($normalized['data'], $this->config->sensitiveDataKeys);
            if ($masked) {
                $changed = true;
            }
        }

        if ($this->config->redactSnapshotPayloads && $artifact instanceof SnapshotInterface && isset($normalized['payload'])) {
            [$normalized['payload'], $masked] = $this->maskKeysRecursive($normalized['payload'], $this->config->sensitiveDataKeys);
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
     * @param  array<mixed, mixed> $data
     * @param  list<string>        $keys
     * @return array{0: array<mixed, mixed>, 1: bool}
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
     * @param  array<mixed, mixed> $throwable
     * @return array{0: array<mixed, mixed>, 1: bool}
     */
    private function maskThrowable(array $throwable): array
    {
        if (array_key_exists('message', $throwable) && is_string($throwable['message'])) {
            $throwable['message'] = $this->config->marker;

            return [$throwable, true];
        }

        return [$throwable, false];
    }
}
