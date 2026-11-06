<?php

declare(strict_types=1);

namespace ItPack\RunIt\SDK\Attribute;

use Closure;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

final class CallableReflectionResolver implements CallableReflectionResolverInterface
{
    /**
     * Also called Paamayim Nekudotayim - @see https://www.php.net/manual/en/language.oop5.paamayim-nekudotayim.php
     */
    private const string DOUBLE_COLON = '::';
    private const string INVOKE_METHOD = '__invoke';

    public function resolve(callable $callable): ?ReflectionFunctionAbstract
    {
        return match (true) {
            is_object($callable) => self::resolveObject($callable),
            is_array($callable),
            is_string($callable) => self::resolveLiteral($callable),
            default => self::resolveFunction($callable),
        };
    }

    private static function resolveObject(callable $callable): ?ReflectionFunctionAbstract
    {
        return $callable instanceof Closure
            ? self::resolveFunction($callable)
            : self::resolveInvokable($callable);
    }

    private static function resolveFunction(callable $callable): ?ReflectionFunctionAbstract
    {
        try {
            return new ReflectionFunction($callable);
        } catch (ReflectionException $e) {
            return null;
        }
    }

    private static function resolveInvokable(callable $callable): ?ReflectionFunctionAbstract
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
}
