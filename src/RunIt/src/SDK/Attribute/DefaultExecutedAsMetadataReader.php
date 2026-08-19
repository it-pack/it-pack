<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

use Closure;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * Reflection-based reader for {@see ExecutedAs} metadata.
 *
 * Resolution happens only when a callable is wrapped, never on the invocation hot path, and
 * the parsed result is cached per reader instance. Reflection is used to locate the function,
 * method, or invokable class a callable refers to; plain closures without a bound method carry
 * no metadata and resolve to null.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class DefaultExecutedAsMetadataReader implements ExecutedAsMetadataReaderInterface
{
    /** @var array<string, ?ExecutedAs> */
    private array $cache = [];

    public function read(callable $operation): ?ExecutedAs
    {
        $key = $this->key($operation);

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $reflection = $this->reflect($operation);
        $metadata = null;

        if ($reflection !== null) {
            $attributes = $reflection->getAttributes(ExecutedAs::class);

            if ($attributes === [] && $reflection instanceof ReflectionMethod) {
                $attributes = $reflection->getDeclaringClass()->getAttributes(ExecutedAs::class);
            }

            if ($attributes !== []) {
                $metadata = $attributes[0]->newInstance();
            }
        }

        $this->cache[$key] = $metadata;

        return $metadata;
    }

    /**
     * @return array<string, ?ExecutedAs> snapshot of the resolved metadata cache.
     */
    public function cache(): array
    {
        return $this->cache;
    }

    private function key(callable $operation): string
    {
        if (is_array($operation)) {
            $class = is_object($operation[0]) ? $operation[0]::class : $operation[0];

            return $class . '::' . $operation[1];
        }

        if (is_string($operation)) {
            return $operation;
        }

        if ($operation instanceof Closure) {
            $reflection = new ReflectionFunction($operation);
            $called = $reflection->getClosureCalledClass();

            if ($called !== null) {
                return $called->getName() . '::' . $this->calledMethodName($reflection);
            }

            return 'closure@' . spl_object_hash($operation);
        }

        if (is_object($operation)) {
            return $operation::class . '::__invoke';
        }

        return 'unknown::__invoke';
    }

    private function calledMethodName(ReflectionFunction $reflection): string
    {
        $name = $reflection->getName();
        $position = strrpos($name, '::');

        return $position === false ? $name : substr($name, $position + 2);
    }

    private function reflect(callable $operation): ?ReflectionFunctionAbstract
    {
        try {
            if (is_array($operation)) {
                return new ReflectionMethod($operation[0], $operation[1]);
            }

            if (is_string($operation)) {
                if (str_contains($operation, '::') && class_exists(explode('::', $operation, 2)[0])) {
                    return new ReflectionMethod($operation);
                }

                return new ReflectionFunction($operation);
            }

            if ($operation instanceof Closure) {
                $reflection = new ReflectionFunction($operation);
                $called = $reflection->getClosureCalledClass();

                if ($called !== null) {
                    return new ReflectionMethod($called->getName(), $this->calledMethodName($reflection));
                }

                return $reflection;
            }

            if (is_object($operation)) {
                return new ReflectionMethod($operation, '__invoke');
            }

            return null;
        } catch (ReflectionException) {
            return null;
        }
    }

}
