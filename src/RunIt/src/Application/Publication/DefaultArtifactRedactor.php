<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;
use RuntimeException;
use Throwable;

/**
 * Redactor that masks sensitive values in an artifact's normalized projection.
 *
 * It operates on the artifact's `normalize()` output so it never mutates the
 * original: when something must be masked it returns a {@see RedactedArtifact}
 * with the masked projection; when nothing matches it returns the same artifact.
 *
 * Masking covers:
 *
 *  - top-level attribute and extension attribute values whose key is in
 *    `sensitiveAttributeKeys`;
 *  - observation `message` when `redactObservationMessages` is enabled (default true; setting `false` opts out of masking observation messages);
 *  - throwable messages and causes, and omission of traces, when enabled;
 *  - measurement values, when enabled;
 *  - configured keys and throwable fields in occurrence `data` and snapshot
 *    `payload`, recursively, when their respective options are enabled.
 *
 * Only configured fields and keys are masked; other normalized fields are not
 * validated or classified. Only arrays are traversed; recursive traversal is
 * bounded and may replace or truncate data. When traversal limits are exhausted
 * the excess is replaced by the marker and, when a health reporter is configured,
 * reported through the health path. A nested array named `throwable` is treated
 * as a throwable projection regardless of its contents.
 *
 * The redactor stamps the trusted {@see RedactedArtifact::artifactType()} on every
 * projection it produces so adapters route redacted artifacts by signal, not by
 * inspecting user-controlled normalized fields.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class DefaultArtifactRedactor implements RedactorInterface
{
    private const int MAX_MASKING_DEPTH = 16;
    private const int MAX_MASKING_NODES = 1024;
    private const int MAX_THROWABLE_CLASS_LENGTH = 256;

    private bool $publishingHealthReport = false;
    private bool $truncationReported = false;

    public function __construct(
        private ArtifactRedactionConfig $config = new ArtifactRedactionConfig(),
        private ?InternalHealthReporterInterface $health = null,
    ) {
    }

    public function redact(ExecutionArtifactInterface $artifact): ExecutionArtifactInterface
    {
        $normalized = $artifact->normalize();

        $changed = false;
        $truncated = false;

        [$normalized, $masked, $truncated] = $this->maskFieldAttributes(
            ArtifactField::ATTRIBUTES,
            $normalized,
            $truncated,
        );
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskFieldAttributes(
            ArtifactField::EXTENSION_ATTRIBUTES,
            $normalized,
            $truncated,
        );
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskThrowableField($normalized, $truncated);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskObservationMessage($normalized, $artifact, $truncated);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskMeasurementValue($normalized, $artifact, $truncated);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskExecutionResultThrowable($normalized, $artifact, $truncated);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskSnapshotAttributes($normalized, $artifact, $truncated);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskOccurrenceData($normalized, $artifact, $truncated);
        if ($masked) {
            $changed = true;
        }

        [$normalized, $masked, $truncated] = $this->maskSnapshotPayload($normalized, $artifact, $truncated);
        if ($masked) {
            $changed = true;
        }

        if (!$changed) {
            return $artifact;
        }

        if ($truncated && $this->health instanceof InternalHealthReporterInterface) {
            $this->reportTruncation($normalized);
        }

        return new RedactedArtifact($normalized, $this->artifactType($artifact));
    }

    /**
     * @param array<string, mixed>       $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskFieldAttributes(string $field, array $normalized, bool $truncated): array
    {
        if ($this->config->sensitiveAttributeKeys === []
            || !isset($normalized[$field])
            || !is_array($normalized[$field])
        ) {
            return [$normalized, false, $truncated];
        }

        [$normalized[$field], $masked] = $this->maskAttributeKeys(
            $normalized[$field],
            $this->config->sensitiveAttributeKeys
        );

        return [$normalized, $masked, $truncated];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskThrowableField(array $normalized, bool $truncated): array
    {
        if (!$this->config->redactThrowableMessages
            || !isset($normalized[ArtifactField::THROWABLE])
            || !is_array($normalized[ArtifactField::THROWABLE])
        ) {
            return [$normalized, false, $truncated];
        }

        [$normalized[ArtifactField::THROWABLE], $masked, $truncated] = $this->maskThrowable(
            $normalized[ArtifactField::THROWABLE],
            0,
            $truncated,
        );

        return [$normalized, $masked, $truncated];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskObservationMessage(
        array $normalized,
        ExecutionArtifactInterface $artifact,
        bool $truncated,
    ): array {
        if (!$this->config->redactObservationMessages
            || !$artifact instanceof ObservationInterface
            || !isset($normalized[ArtifactField::MESSAGE])
            || !is_string($normalized[ArtifactField::MESSAGE])
        ) {
            return [$normalized, false, $truncated];
        }

        $normalized[ArtifactField::MESSAGE] = $this->config->marker;

        return [$normalized, true, $truncated];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskMeasurementValue(
        array $normalized,
        ExecutionArtifactInterface $artifact,
        bool $truncated,
    ): array {
        if (!$this->config->redactMeasurementValues
            || !$artifact instanceof MeasurementInterface
            || !isset($normalized[ArtifactField::VALUE])
        ) {
            return [$normalized, false, $truncated];
        }

        $normalized[ArtifactField::VALUE] = $this->config->marker;

        return [$normalized, true, $truncated];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskExecutionResultThrowable(
        array $normalized,
        ExecutionArtifactInterface $artifact,
        bool $truncated,
    ): array {
        if (!$this->config->redactThrowableMessages
            || !$artifact instanceof ExecutionInterface
            || !is_array($normalized[ArtifactField::RESULT])
            || !isset($normalized[ArtifactField::RESULT][ArtifactField::THROWABLE])
            || !is_array($normalized[ArtifactField::RESULT][ArtifactField::THROWABLE])
        ) {
            return [$normalized, false, $truncated];
        }

        [$normalized[ArtifactField::RESULT][ArtifactField::THROWABLE], $masked, $truncated] = $this->maskThrowable(
            $normalized[ArtifactField::RESULT][ArtifactField::THROWABLE],
            0,
            $truncated,
        );

        return [$normalized, $masked, $truncated];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskSnapshotAttributes(
        array $normalized,
        ExecutionArtifactInterface $artifact,
        bool $truncated,
    ): array {
        if ($this->config->sensitiveAttributeKeys === []
            || !$artifact instanceof SnapshotInterface
            || !isset($normalized[ArtifactField::ATTRIBUTES])
            || !is_array($normalized[ArtifactField::ATTRIBUTES])
        ) {
            return [$normalized, false, $truncated];
        }

        [$normalized[ArtifactField::ATTRIBUTES], $masked] = $this->maskAttributeKeys(
            $normalized[ArtifactField::ATTRIBUTES],
            $this->config->sensitiveAttributeKeys
        );

        return [$normalized, $masked, $truncated];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskOccurrenceData(array $normalized, ExecutionArtifactInterface $artifact, bool $truncated): array
    {
        if (!$this->config->redactOccurrenceData
            || !$artifact instanceof OccurrenceInterface
            || !isset($normalized[ArtifactField::DATA])
            || !is_array($normalized[ArtifactField::DATA])
        ) {
            return [$normalized, false, $truncated];
        }

        $nodes = 0;
        [$normalized[ArtifactField::DATA], $masked, $truncated] = $this->maskKeysRecursive(
            $normalized[ArtifactField::DATA],
            $this->config->sensitiveDataKeys,
            0,
            $nodes,
            maskThrowables: $this->config->redactThrowableMessages,
            truncated: $truncated,
        );

        return [$normalized, $masked, $truncated];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function maskSnapshotPayload(
        array $normalized,
        ExecutionArtifactInterface $artifact,
        bool $truncated,
    ): array {
        if (!$this->config->redactSnapshotPayloads
            || !$artifact instanceof SnapshotInterface
            || !isset($normalized[ArtifactField::PAYLOAD])
            || !is_array($normalized[ArtifactField::PAYLOAD])
        ) {
            return [$normalized, false, $truncated];
        }

        $nodes = 0;
        [$normalized[ArtifactField::PAYLOAD], $masked, $truncated] = $this->maskKeysRecursive(
            $normalized[ArtifactField::PAYLOAD],
            $this->config->sensitiveDataKeys,
            0,
            $nodes,
            maskThrowables: $this->config->redactThrowableMessages,
            truncated: $truncated,
        );

        return [$normalized, $masked, $truncated];
    }

    /**
     * @param array<mixed, mixed> $data
     * @param list<string>        $keys
     * @return array{0: array<mixed, mixed>|string, 1: bool}
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
     * @param int                 $nodes cumulative traversal count
     * @return array{0: array<mixed, mixed>|string, 1: bool, 2: bool}
     */
    private function maskKeysRecursive(
        array $data,
        array $keys,
        int $depth,
        int &$nodes,
        bool $maskThrowables = false,
        bool $truncated = false
    ): array {
        if ($depth >= self::MAX_MASKING_DEPTH || ++$nodes > self::MAX_MASKING_NODES) {
            return [$this->config->marker, true, true];
        }

        $masked = false;
        $keySet = array_fill_keys($keys, value: true);
        $result = [];

        foreach ($data as $key => $value) {
            if (++$nodes > self::MAX_MASKING_NODES) {
                $result[$key] = $this->config->marker;
                $masked = true;
                $truncated = true;

                break;
            }

            if (isset($keySet[$key])) {
                $result[$key] = $this->config->marker;
                $masked = true;

                continue;
            }

            if ($maskThrowables && $key === ArtifactField::THROWABLE && is_array($value)) {
                [$result[$key], $innerMasked, $innerTruncated] = $this->maskThrowable($value, $depth + 1, $truncated);
                if ($innerMasked) {
                    $masked = true;
                }

                if ($innerTruncated) {
                    $truncated = true;
                }

                continue;
            }

            if (!is_array($value)) {
                $result[$key] = $value;

                continue;
            }

            [$result[$key], $innerMasked, $innerTruncated] = $this->maskKeysRecursive(
                $value,
                $keys,
                $depth + 1,
                $nodes,
                $maskThrowables,
                $truncated,
            );

            if ($innerMasked) {
                $masked = true;
            }

            if ($innerTruncated) {
                $truncated = true;
            }
        }

        return [$result, $masked, $truncated];
    }

    /**
     * @param array<mixed, mixed> $throwable
     * @return array{0: array<mixed, mixed>, 1: bool, 2: bool}
     */
    private function maskThrowable(array $throwable, int $depth = 0, bool $truncated = false): array
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
            if ($previous === null) {
                // Chain ends normally: previous is null, not a traversal-limit event.
                $safe[ArtifactField::PREVIOUS] = null;
            }
            if ($previous !== null && is_array($previous) && $depth < 15) {
                $maskedPrevious = $this->maskThrowable($previous, $depth + 1, $truncated);
                $safe[ArtifactField::PREVIOUS] = $maskedPrevious[0];
                if ($maskedPrevious[1] || $safe[ArtifactField::PREVIOUS] !== $previous) {
                    $changed = true;
                }
                if ($maskedPrevious[2]) {
                    $truncated = true;
                }
            }
            if ($previous !== null && (!is_array($previous) || $depth >= 15)) {
                // Beyond depth limit or unsupported shape: truncated.
                $safe[ArtifactField::PREVIOUS] = null;
                $changed = true;
                $truncated = true;
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

        return [$safe, $changed, $truncated];
    }

    private function artifactType(ExecutionArtifactInterface $artifact): string
    {
        if ($artifact instanceof RedactedArtifactInterface) {
            // A redacted artifact already carries its trusted type from the
            // redactor that produced it; preserve it through redaction.
            return $artifact->artifactType();
        }

        return match (true) {
            $artifact instanceof ExecutionInterface => 'execution',
            $artifact instanceof ObservationInterface => 'observation',
            $artifact instanceof ActivityInterface => 'activity',
            $artifact instanceof MeasurementInterface => 'measurement',
            $artifact instanceof OccurrenceInterface => 'occurrence',
            $artifact instanceof SnapshotInterface => 'snapshot',
            default => 'unknown',
        };
    }

    /**
     * Reports that redaction traversal limits were exceeded and data was truncated.
     *
     * Only a bounded, payload-free notice is sent through the health channel so
     * no redacted attribute payload leaks through the health reporter. Health
     * reporter failures are contained so truncation reporting never changes the
     * business result.
     *
     * @param array<string, mixed> $normalized
     */
    private function reportTruncation(array $normalized): void
    {
        if ($this->publishingHealthReport) {
            // Re-entry guard: health reporting must not re-enter the same
            // publication path. Skip the health notice and drop the truncation
            // notice fail-open.
            return;
        }

        if ($this->truncationReported) {
            // Deduplicate truncation reports so the hot path does not allocate
            // and report on every oversized artifact.
            return;
        }

        $id = is_string($normalized[ArtifactField::ID] ?? null)
            ? $normalized[ArtifactField::ID]
            : 'redacted';
        if (strlen($id) > StringIdentifierValidator::MAX_LENGTH) {
            $id = substr($id, 0, StringIdentifierValidator::MAX_LENGTH - 3) . '...';
        }

        try {
            $this->publishingHealthReport = true;
            $this->truncationReported = true;
            $this->health?->report(
                new RuntimeException('Artifact redaction traversal limits exceeded; sensitive data was truncated.'),
                new RedactedArtifact([
                    ArtifactField::ID => $id,
                    ArtifactField::TYPE => 'runit.artifact.redacted',
                ]),
            );
        } catch (Throwable) {
            // Contain health reporter failures so truncation reporting stays fail-open.
        } finally {
            $this->publishingHealthReport = false;
        }
    }
}
