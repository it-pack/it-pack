<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

use ItPack\CommandIt\Contract\Command\ArgumentInterface;
use ItPack\CommandIt\Contract\Command\OptionInterface;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;

/**
 * Stateless coercion + resolution for command input values.
 *
 * Shared by the core {@see \ItPack\CommandIt\Application\CommandInputParser} and
 * the {@see \ItPack\CommandIt\Adapter\Input\ArrayInputAdapter} so both layers
 * coerce and validate identically without either depending on the other (the
 * architecture forbids Adapter → Application). It lives in the Domain layer,
 * which both may use.
 *
 * @internal
 */
trait InputCoercionBehavior
{
    /**
     * Coerces a raw input value to the expected PHP type name.
     *
     * @return mixed
     */
    protected function coerceInputValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => \is_scalar($value) ? (int) $value : 0,
            'float', 'double' => \is_scalar($value) ? (float) $value : 0.0,
            'bool' => $this->coerceBool($value),
            'string' => \is_scalar($value) ? (string) $value : '',
            default => $value,
        };
    }

    /**
     * Resolves a set of specs (arguments or options) against provided values.
     *
     * Required entries that are absent throw; optional entries fall back to their
     * default value when present, otherwise are omitted.
     *
     * @param list<ArgumentInterface|OptionInterface> $specs
     * @param array<string, mixed>                    $provided
     *
     * @return array<string, mixed>
     */
    protected function resolveInputs(array $specs, array $provided, string $kind): array
    {
        $out = [];

        foreach ($specs as $spec) {
            $name = $spec->name()->toString();

            if (\array_key_exists($name, $provided)) {
                $out[$name] = $this->coerceInputValue($provided[$name], $spec->type());

                continue;
            }

            if ($spec->required()) {
                throw InvalidCommandException::malformed(\sprintf('Missing required %s "%s".', $kind, $name));
            }

            if ($spec->defaultValue() !== null) {
                $out[$name] = $this->coerceInputValue($spec->defaultValue(), $spec->type());
            }
        }

        return $out;
    }

    private function coerceBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return match (\strtolower($value)) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => (bool) $value,
            };
        }

        return \is_scalar($value) ? (bool) $value : false;
    }
}
