<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
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
 *  - observation extension attributes whose key is in `sensitiveAttributeKeys`;
 *  - observation `message` when `redactObservationMessages` is enabled (default true);
 *  - throwable messages and traces, when enabled;
 *  - measurement values, when enabled;
 *  - occurrence `data`, recursively, when enabled;
 *  - snapshot `payload`, recursively, when enabled;
 *  - snapshot attributes whose key is in `sensitiveAttributeKeys`.
 *
 * Only configured fields and keys are masked; other normalized fields are not
 * validated or classified. Recursive traversal is bounded and may truncate data.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class DefaultArtifactRedactor implements RedactorInterface
{
    private const int MAX_MASKING_DEPTH = 16;

    private const int MAX_MASKING_NODES = 1024;

    private const int MAX_THROWABLE_CLASS_LENGTH = 256;

    public function __construct(
        private ArtifactRedactionConfig $config = new ArtifactRedactionConfig(),
    ) {
    }

    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        $normalized = $artifact->normalize();

        $changed = false;

        [$normalized, $masked] = $this->maskFieldAttributes(ArtifactField::ATTRIBUTES, $normalized);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskFieldAttributes(ArtifactField::EXTENSION_ATTRIBUTES, $normalized);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskThrowableField($normalized);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskObservationMessage($normalized, $artifact);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskMeasurementValue($normalized, $artifact);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskExecutionResultThrowable($normalized, $artifact);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskSnapshotAttributes($normalized, $artifact);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskOccurrenceData($normalized, $artifact);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked] = $this->maskSnapshotPayload($normalized, $artifact);
        if ($masked) {
            $changed = true;
        }

        if (!$changed) {
            return $artifact;
        }

        return new RedactedArtifact($normalized);
    }

    /**
     * @param array<string, mixed>       $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskFieldAttributes(string $field, array $normalized): array
    {
        if ($this->config->sensitiveAttributeKeys === []
            || !isset($normalized[$field])
            || !is_array($normalized[$field])
        ) {
            return [$normalized, false];
        }

        [$normalized[$field], $masked] = $this->maskAttributeKeys(
            $normalized[$field],
            $this->config->sensitiveAttributeKeys
        );

        return [$normalized, $masked];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskThrowableField(array $normalized): array
    {
        if (!$this->config->redactThrowableMessages
            || !isset($normalized[ArtifactField::THROWABLE])
            || !is_array($normalized[ArtifactField::THROWABLE])
        ) {
            return [$normalized, false];
        }

        [$normalized[ArtifactField::THROWABLE], $masked] = $this->maskThrowable(
            $normalized[ArtifactField::THROWABLE],
        );

        return [$normalized, $masked];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskObservationMessage(array $normalized, ExecutionArtifactInterface $artifact): array
    {
        if (!$this->config->redactObservationMessages
            || !$artifact instanceof ObservationInterface
            || !isset($normalized[ArtifactField::MESSAGE])
            || !is_string($normalized[ArtifactField::MESSAGE])
        ) {
            return [$normalized, false];
        }

        $normalized[ArtifactField::MESSAGE] = $this->config->marker;

        return [$normalized, true];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskMeasurementValue(array $normalized, ExecutionArtifactInterface $artifact): array
    {
        if (!$this->config->redactMeasurementValues
            || !$artifact instanceof MeasurementInterface
            || !isset($normalized[ArtifactField::VALUE])
        ) {
            return [$normalized, false];
        }

        $normalized[ArtifactField::VALUE] = $this->config->marker;

        return [$normalized, true];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskExecutionResultThrowable(array $normalized, ExecutionArtifactInterface $artifact): array
    {
        if (!$this->config->redactThrowableMessages
            || !$artifact instanceof ExecutionInterface
            || !is_array($normalized[ArtifactField::RESULT])
            || !isset($normalized[ArtifactField::RESULT][ArtifactField::THROWABLE])
            || !is_array($normalized[ArtifactField::RESULT][ArtifactField::THROWABLE])
        ) {
            return [$normalized, false];
        }

        [$normalized[ArtifactField::RESULT][ArtifactField::THROWABLE], $masked] = $this->maskThrowable(
            $normalized[ArtifactField::RESULT][ArtifactField::THROWABLE],
        );

        return [$normalized, $masked];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskSnapshotAttributes(array $normalized, ExecutionArtifactInterface $artifact): array
    {
        if ($this->config->sensitiveAttributeKeys === []
            || !$artifact instanceof SnapshotInterface
            || !isset($normalized[ArtifactField::ATTRIBUTES])
            || !is_array($normalized[ArtifactField::ATTRIBUTES])
        ) {
            return [$normalized, false];
        }

        [$normalized[ArtifactField::ATTRIBUTES], $masked] = $this->maskAttributeKeys(
            $normalized[ArtifactField::ATTRIBUTES],
            $this->config->sensitiveAttributeKeys
        );

        return [$normalized, $masked];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskOccurrenceData(array $normalized, ExecutionArtifactInterface $artifact): array
    {
        if (!$this->config->redactOccurrenceData
            || !$artifact instanceof OccurrenceInterface
            || !isset($normalized[ArtifactField::DATA])
            || !is_array($normalized[ArtifactField::DATA])
        ) {
            return [$normalized, false];
        }

        [$normalized[ArtifactField::DATA], $masked] = $this->maskKeysRecursive(
            $normalized[ArtifactField::DATA],
            $this->config->sensitiveDataKeys,
            maskThrowables: $this->config->redactThrowableMessages,
        );

        return [$normalized, $masked];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function maskSnapshotPayload(array $normalized, ExecutionArtifactInterface $artifact): array
    {
        if (!$this->config->redactSnapshotPayloads
            || !$artifact instanceof SnapshotInterface
            || !isset($normalized[ArtifactField::PAYLOAD])
            || !is_array($normalized[ArtifactField::PAYLOAD])
        ) {
            return [$normalized, false];
        }

        [$normalized[ArtifactField::PAYLOAD], $masked] = $this->maskKeysRecursive(
            $normalized[ArtifactField::PAYLOAD],
            $this->config->sensitiveDataKeys,
            maskThrowables: $this->config->redactThrowableMessages,
        );

        return [$normalized, $masked];
    }

    /**
     * @param array<mixed, mixed> $data
     * @param list<string>        $keys
     * @return array{0: array<mixed, mixed>, 1: bool}
     */
    private function maskAttributeKeys(array $data, array $keys): array
    {
        $masked = false;
        $keySet = array_fill_keys($keys, value: true);

        foreach ($data as $key => $value) {
            if (isset($keySet[$key])) {
                $data[$key] = $this->config->marker;
                $masked = true;
            }
        }

        return [$data, $masked];
    }

    /**
     * @param array<mixed, mixed> $data
     * @param list<string>        $keys
     * @param bool                $maskThrowables also mask nested throwable fields
     * @param-out int             $nodes
     * @return array{0: array<mixed, mixed>|string, 1: bool}
     */
    private function maskKeysRecursive(
        array $data,
        array $keys,
        int $depth = 0,
        ?int &$nodes = null,
        bool $maskThrowables = false
    ): array {
        $nodes ??= 0;
        if ($depth >= self::MAX_MASKING_DEPTH || ++$nodes > self::MAX_MASKING_NODES) {
            return [$this->config->marker, true];
        }

        $masked = false;
        $keySet = array_fill_keys($keys, value: true);
        $result = [];

        foreach ($data as $key => $value) {
            if (++$nodes > self::MAX_MASKING_NODES) {
                $result[$key] = $this->config->marker;
                $masked = true;

                break;
            }

            if (isset($keySet[$key])) {
                $result[$key] = $this->config->marker;
                $masked = true;

                continue;
            }

            if ($maskThrowables && $key === ArtifactField::THROWABLE && is_array($value)) {
                [$result[$key], $innerMasked] = $this->maskThrowable($value);
                if ($innerMasked) {
                    $masked = true;
                }

                continue;
            }

            if (!is_array($value)) {
                $result[$key] = $value;

                continue;
            }

            [$result[$key], $innerMasked] = $this->maskKeysRecursive(
                $value,
                $keys,
                $depth + 1,
                $nodes,
                $maskThrowables
            );

            if ($innerMasked) {
                $masked = true;
            }
        }

        return [$result, $masked];
    }

    /**
     * @param array<mixed, mixed> $throwable
     * @return array{0: array<mixed, mixed>, 1: bool}
     */
    private function maskThrowable(array $throwable, int $depth = 0): array
    {
        $safe = [];
        $changed = false;
        $class = $throwable[ArtifactField::THROWABLE_CLASS] ?? null;
        $code = $throwable[ArtifactField::CODE] ?? null;
        if (is_string($class) && strlen($class) <= self::MAX_THROWABLE_CLASS_LENGTH
            && preg_match('/\A[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff\\\\]*\z/', $class) === 1) {
            $safe[ArtifactField::THROWABLE_CLASS] = $class;
            if (!isset($throwable[ArtifactField::THROWABLE_CLASS])
                || $throwable[ArtifactField::THROWABLE_CLASS] !== $class) {
                $changed = true;
            }
        }

        if (is_int($code)) {
            $safe[ArtifactField::CODE] = $code;
            if (!isset($throwable[ArtifactField::CODE]) || $throwable[ArtifactField::CODE] !== $code) {
                $changed = true;
            }
        }

        if (array_key_exists(ArtifactField::MESSAGE, $throwable)) {
            $safe[ArtifactField::MESSAGE] = $this->config->marker;
            if ($throwable[ArtifactField::MESSAGE] !== $this->config->marker) {
                $changed = true;
            }
        }

        if (array_key_exists(ArtifactField::PREVIOUS, $throwable)) {
            $previous = $throwable[ArtifactField::PREVIOUS];
            $maskedPrevious = is_array($previous) && $depth < 15
                ? $this->maskThrowable($previous, $depth + 1)
                : [null, false];
            $safe[ArtifactField::PREVIOUS] = $maskedPrevious[0];
            if ($maskedPrevious[1] || $safe[ArtifactField::PREVIOUS] !== $previous) {
                $changed = true;
            }
        }

        if (array_key_exists(ArtifactField::TRACE, $throwable)) {
            $safe[ArtifactField::TRACE] = null;
            if ($throwable[ArtifactField::TRACE] !== null) {
                $changed = true;
            }
        }

        foreach ($throwable as $field => $value) {
            if (!array_key_exists($field, $safe) || $safe[$field] !== $value) {
                $changed = true;

                break;
            }
        }

        return [$safe, $changed];
    }
}
