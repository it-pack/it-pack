<?php

declare(strict_types=1);

namespace Tests\Unit\SDK\Attribute;

use ItPack\RunIt\SDK\Attribute\CallableReflectionResolver;
use Tests\Support\Fixture\SDK\ExtendedSampleService;
use Tests\Support\Fixture\SDK\SampleInvokable;
use Tests\Support\Fixture\SDK\SampleService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;

#[CoversClass(CallableReflectionResolver::class)]
final class CallableReflectionResolverTest extends TestCase
{
    private CallableReflectionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CallableReflectionResolver();
    }

    public function testResolveMethodString(): void
    {
        $class = SampleService::class;
        $method = 'statically';
        $methodString = sprintf('%s::%s', $class, $method);

        $reflection = $this->resolver->resolve($methodString);

        $this->assertInstanceOf(ReflectionMethod::class, $reflection);
        $this->assertSame($class, $reflection->getDeclaringClass()->getName());
        $this->assertSame($method, $reflection->getName());
    }

    public function testResolveMethodArray(): void
    {
        $class = SampleService::class;
        $method = 'statically';

        $reflection = $this->resolver->resolve([$class, $method]);

        $this->assertInstanceOf(ReflectionMethod::class, $reflection);
        $this->assertSame($class, $reflection->getDeclaringClass()->getName());
        $this->assertSame($method, $reflection->getName());
    }

    public function testResolveObjectMethodArray(): void
    {
        $class = SampleService::class;
        $method = 'statically';

        $reflection = $this->resolver->resolve([new $class, $method]);

        $this->assertInstanceOf(ReflectionMethod::class, $reflection);
        $this->assertSame($class, $reflection->getDeclaringClass()->getName());
        $this->assertSame($method, $reflection->getName());
    }

    public function testResolveInvokable(): void
    {
        $class = SampleInvokable::class;
        $method = '__invoke';

        $reflection = $this->resolver->resolve(new $class);

        $this->assertInstanceOf(ReflectionMethod::class, $reflection);
        $this->assertSame($class, $reflection->getDeclaringClass()->getName());
        $this->assertSame($method, $reflection->getName());
    }

    public function testClosureFromMethod(): void
    {
        $class = SampleService::class;
        $method = 'run';

        $reflection = $this->resolver->resolve(
            new $class()->{$method}(...),
        );
        $this->assertSame($method, $reflection->getName());
    }

    public function testClosureFromExtendedClassMethod(): void
    {
        $class = ExtendedSampleService::class;
        $method = 'run';

        $reflection = $this->resolver->resolve(
            new $class()->{$method}(...),
        );
        $this->assertSame($method, $reflection->getName());
    }

    public function testFunctionString(): void
    {
        $function = 'Tests\Support\Fixture\SDK\sample_free_function';

        $reflection = $this->resolver->resolve($function);

        $this->assertInstanceOf(ReflectionFunction::class, $reflection);
        $this->assertSame($function, $reflection->getName());
    }

    public function testLambdaFunction(): void
    {
        $function = static fn() => true;

        $reflection = $this->resolver->resolve($function);

        $this->assertInstanceOf(ReflectionFunction::class, $reflection);
    }

    public function testClosure(): void
    {
        $function = static fn() => true;

        $reflection = $this->resolver->resolve($function(...));

        $this->assertInstanceOf(ReflectionFunction::class, $reflection);
    }
}
