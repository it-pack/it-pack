<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

use Closure;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use WeakMap;

/**
 * Reflection-based reader for {@see ExecutedAs} metadata.
 *
 * Resolution happens only when a callable is wrapped, never on the invocation hot path, and
 * the parsed result is cached per reader instance. Reflection is used to locate the function,
 * method, or invokable class a callable refers to; plain closures without a bound method carry
 * no metadata and resolve to null.
 *
 * Object-based callables (closures and invokable instances) are cached in a {@see WeakMap} keyed
 * on the callable object itself rather than on a `spl_object_hash` string. Keying on the object
 * identity means a newly created callable can never reuse a freed object's hash and inherit stale
 * metadata, and the entry is released automatically once the callable is no longer referenced, so
 * the cache cannot grow unbounded across many distinct callables. Callable strings and
 * `[class, method]` arrays keep a small string-keyed cache keyed on their stable, collision-free
 * name.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class DefaultExecutedAsMetadataReader implements ExecutedAsMetadataReaderInterface
{
    /** @var WeakMap<object, ?ExecutedAs> */
    private WeakMap $objectCache;

    /** @var array<string, ?ExecutedAs> */
    private array $literalCache = [];

    public function __construct()
    {
        $this->objectCache = new WeakMap();
    }

    public function read(callable|Closure $operation): ?ExecutedAs
    {
        if (is_object($operation)) {
            return $this->readObjectKeyed($operation);
        }

        $key = $this->literalKey($operation);

        if (array_key_exists($key, $this->literalCache)) {
            return $this->literalCache[$key];
        }

        $metadata = $this->resolveFromReflection($this->reflectLiteral($operation));

        $this->literalCache[$key] = $metadata;

        return $metadata;
    }

    private function readObjectKeyed(object $operation): ?ExecutedAs
    {
        if ($this->objectCache->offsetExists($operation)) {
            return $this->objectCache->offsetGet($operation);
        }

        $metadata = $this->resolveFromReflection($this->reflectObject($operation));

        $this->objectCache->offsetSet($operation, $metadata);

        return $metadata;
    }

    private function resolveFromReflection(?ReflectionFunctionAbstract $reflection): ?ExecutedAs
    {
        if ($reflection === null) {
            return null;
        }

        $attributes = $reflection->getAttributes(ExecutedAs::class);

        if ($attributes === [] && $reflection instanceof ReflectionMethod) {
            $attributes = $reflection->getDeclaringClass()->getAttributes(ExecutedAs::class);
        }

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function literalKey(callable $operation): string
    {
        if (is_array($operation)) {
            $class = is_object($operation[0]) ? $operation[0]::class : $operation[0];

            return $class . '::' . $operation[1];
        }

        if (is_string($operation)) {
            return $operation;
        }

        throw new \LogicException('Object callables are resolved through the WeakMap cache.');
    }

    private function calledMethodName(ReflectionFunction $reflection): string
    {
        $name = $reflection->getName();
        $position = strrpos($name, '::');

        return $position === false ? $name : substr($name, $position + 2);
    }

    private function reflectObject(object $operation): ?ReflectionFunctionAbstract
    {
        try {
            if ($operation instanceof Closure) {
                $reflection = new ReflectionFunction($operation);
                $called = $reflection->getClosureCalledClass();

                if ($called !== null) {
                    return new ReflectionMethod($called->getName(), $this->calledMethodName($reflection));
                }

                return $reflection;
            }

            return new ReflectionMethod($operation, '__invoke');
        } catch (ReflectionException) {
            return null;
        }
    }

    private function reflectLiteral(callable $operation): ?ReflectionFunctionAbstract
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

            throw new \LogicException('Object callables are resolved through the WeakMap cache.');
        } catch (ReflectionException) {
            return null;
        }
    }

}
