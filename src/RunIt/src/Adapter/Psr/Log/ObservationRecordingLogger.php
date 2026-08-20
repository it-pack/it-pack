<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Log;

use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationGuardInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationGuardTokenInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use Psr\Log\AbstractLogger;
use Throwable;

/**
 * Bridges a PSR-3 logger to RunIt observations.
 *
 * Any log call is mapped to a RunIt observation: the PSR-3 level becomes the
 * observation severity, the message is kept as the stable template, and the
 * logger context becomes the observation attributes (with the `exception`
 * context value, when it is a {@see Throwable}, mapped through the recorder's
 * throwable policy). Non-scalar context values are dropped so observations never
 * carry objects or resources.
 *
 * A recursive-publication guard prevents an observation published by this bridge
 * from triggering another log-and-record cycle: when publication is already
 * active on the current path the bridge returns without recording. This stops the
 * logging-to-observation-to-logging loop described in the roadmap.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final class ObservationRecordingLogger extends AbstractLogger
{
    /**
     * @param list<string> $reservedContextKeys context keys consumed by the bridge and
     *        excluded from observation attributes
     */
    public function __construct(
        private readonly ObservationRecorderInterface $recorder,
        private readonly ?PublicationGuardInterface $guard = null,
        private readonly array $reservedContextKeys = ['exception'],
    ) {
    }

    /**
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($this->guard instanceof PublicationGuardInterface && $this->guard->isActive()) {
            return;
        }

        $token = $this->guard instanceof PublicationGuardInterface ? $this->guard->enter() : null;

        try {
            $throwable = isset($context['exception']) && $context['exception'] instanceof Throwable ? $context['exception'] : null;
            $attributes = $this->attributesFromContext($context);

            $this->recorder->record(
                (string) $message,
                $this->severityForLevel($this->levelToString($level)),
                AttributeCollection::fromArray($attributes),
                $throwable,
            );
        } finally {
            if ($token instanceof PublicationGuardTokenInterface) {
                $this->guard->leave($token);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $context
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    private function attributesFromContext(array $context): array
    {
        $attributes = [];

        foreach ($context as $key => $value) {
            if (in_array($key, $this->reservedContextKeys, true)) {
                continue;
            }

            if ($this->isScalarOrScalarMap($value)) {
                /** @var int|float|string|bool|null|array<int|string, int|float|string|bool|null> $value */
                $attributes[(string) $key] = $value;
            }
        }

        return $attributes;
    }

    private function isScalarOrScalarMap(mixed $value): bool
    {
        if (is_object($value) || is_resource($value)) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_object($item) || is_resource($item)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function levelToString(mixed $level): string
    {
        if (is_object($level) && method_exists($level, '__toString')) {
            return (string) $level;
        }

        if (is_scalar($level)) {
            return (string) $level;
        }

        return 'debug';
    }

    private function severityForLevel(string $level): ObservationSeverity
    {
        return match (strtolower($level)) {
            'emergency' => ObservationSeverity::Emergency,
            'alert' => ObservationSeverity::Alert,
            'critical' => ObservationSeverity::Critical,
            'error' => ObservationSeverity::Error,
            'warning' => ObservationSeverity::Warning,
            'notice' => ObservationSeverity::Notice,
            'info' => ObservationSeverity::Info,
            default => ObservationSeverity::Debug,
        };
    }
}
