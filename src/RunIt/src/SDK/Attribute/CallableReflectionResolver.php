<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class CallableReflectionResolver implements CallableReflectionResolverInterface
{
    /**
     * Also called Paamayim Nekudotayim - @see https://www.php.net/manual/en/language.oop5.paamayim-nekudotayim.php
     */
    private const string DOUBLE_COLON = '::';
    private const string INVOKE_METHOD = '__invoke';

    public function resolve(callable|Closure $callable): ?ReflectionFunctionAbstract
    {
        return is_object($callable)
            ? self::resolveObject($callable)
            : self::resolveLiteral($callable);
    }

    private static function resolveObject(object $callable): ?ReflectionFunctionAbstract
    {
        return $callable instanceof Closure
            ? self::resolveClosure($callable)
            : self::resolveInvokable($callable);
    }

    private static function resolveClosure(Closure $callable): ?ReflectionFunctionAbstract
    {
        try {
            $reflection = new ReflectionFunction($callable);
            $called = $reflection->getClosureCalledClass();

            return $called instanceof ReflectionClass
                ? $reflection
                : new ReflectionMethod(
                    $called->getName(),
                    self::calledMethodName($reflection)
                );

        } catch (ReflectionException) {
            return null;
        }
    }

    private static function resolveInvokable(object $callable): ?ReflectionFunctionAbstract
    {
        return method_exists($callable, self::INVOKE_METHOD)
            ? new ReflectionMethod($callable, self::INVOKE_METHOD)
            : null;
    }

    private static function resolveLiteral(string|array $callable): ?ReflectionFunctionAbstract
    {
        try {
            return is_string($callable)
                ? match (true) {
                    self::isMethod($callable) => new ReflectionMethod($callable),
                    function_exists ($callable) => new ReflectionFunction($callable),
                    default => null,
                }
                : new ReflectionMethod(...$callable);

        } catch (ReflectionException) {
            return null;
        }
    }

    private static function isMethod(callable $callable): bool
    {
        return str_contains($callable, self::DOUBLE_COLON)
            && method_exists(...explode(self::DOUBLE_COLON, $callable, 2));
    }

    private static function calledMethodName(ReflectionFunction $reflection): string
    {
        $name = $reflection->getName();
        $position = strrpos($name, self::DOUBLE_COLON);

        return $position === false ? $name : substr($name, $position + 2);
    }
}
