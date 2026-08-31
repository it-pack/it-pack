<?php

declare(strict_types=1);

namespace ItPack\CommandIt\Behavior\Domain;

use ItPack\CommandIt\Contract\Command\ArgumentInterface;
use ItPack\CommandIt\Contract\Command\OptionInterface;
use ItPack\CommandIt\Contract\Exception\InvalidCommandException;
use LogicException;

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
     * Boolean truth aliases accepted (case-insensitively) by {@see self::coerceBool()}.
     */
    private const array BOOL_TRUE_ALIASES = ['1', 'true', 'yes', 'on'];

    /**
     * Boolean falsity aliases accepted (case-insensitively) by {@see self::coerceBool()}.
     */
    private const array BOOL_FALSE_ALIASES = ['0', 'false', 'no', 'off'];

    /**
     * Coerces a raw input value to the expected PHP type name.
     *
     * Scalars are cast; a non-scalar (array, object, resource, null, closure) for
     * an `int`/`float`/`double`/`string` argument is rejected rather than silently
     * zeroed/blanked, so malformed or attacker-controlled input cannot slip
     * through as a plausible-but-wrong value. Boolean keeps its friendly
     * aliases (`1/true/yes/on`, `0/false/no/off`). Unknown declared types are
     * passed through verbatim (e.g. `array`).
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     *
     */
    protected function coerceInputValue(mixed $value, string $type): mixed
    {
        $type = InputType::normalize($type);

        return match ($type) {
            InputType::INT, InputType::FLOAT, InputType::STRING => $this->coerceScalar($value, $type),
            InputType::BOOL => $this->coerceBool($value),
            default => $value,
        };
    }

    /**
     * Casts a scalar to the requested type, rejecting non-scalars.
     *
     * @return int|float|string
     *
     * @throws InvalidCommandException
     */
    private function coerceScalar(mixed $value, string $type): int|float|string
    {
        if (!\is_scalar($value)) {
            throw InvalidCommandException::malformed(
                \sprintf('Expected a scalar %s value, got %s.', $type, \get_debug_type($value)),
            );
        }

        return match ($type) {
            InputType::INT => (int) $value,
            InputType::FLOAT => (float) $value,
            InputType::STRING => (string) $value,
            default => self::rejectUnknownType($type),
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

    /**
     * @param list<ArgumentInterface|OptionInterface> $specs
     *
     * @return list<string>
     */
    final protected function names(array $specs): array
    {
        return \array_map(static fn (ArgumentInterface|OptionInterface $spec): string => $spec->name()->toString(), $specs);
    }

    private function coerceBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $normalized = \strtolower($value);

            if (\in_array($normalized, self::BOOL_TRUE_ALIASES, true)) {
                return true;
            }

            if (\in_array($normalized, self::BOOL_FALSE_ALIASES, true)) {
                return false;
            }

            return (bool) $value;
        }

        return \is_scalar($value) && (bool)$value;
    }

    /**
     * @throws LogicException always — guards the unreachable default arm of {@see self::coerceScalar()}
     */
    private static function rejectUnknownType(string $type): never
    {
        throw new LogicException(\sprintf('Unsupported input type "%s".', $type));
    }
}
