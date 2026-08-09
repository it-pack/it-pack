<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Factory;

use InvalidArgumentException;
use ItPack\RunIt\Contract\Domain\Factory\ImmutableStringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\IdentifierGeneratorInterface;
use ItPack\RunIt\Contract\Domain\ImmutableStringIdentifierInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface;
use ReflectionClass;
use ReflectionException;

readonly class ImmutableStringIdentifierFactory implements ImmutableStringIdentifierFactoryInterface
{
    private ImmutableStringIdentifierInterface $implementation;

    public function __construct(
        string $implementationClass,
        private IdentifierGeneratorInterface $generator,
        private StringIdentifierValidatorInterface $validator,
        bool $testGeneratorOnStart = true,
    ){
        $this->implementation = $this->instantiate(
            $implementationClass,
            $testGeneratorOnStart
        );
    }

    public function build(): ImmutableStringIdentifierInterface
    {
        return new $this->implementation
            ->withValue(
                $this->generator->generate()
        );
    }

    private function validateId(string $value): bool
    {
       return $this->validator->validate($value);
    }

    private function instantiate(string $implementationClass, bool $testGeneratorOnStart)
    {
        self::validateImplementationClass($implementationClass);

        return new $implementationClass();
    }

    private static function validateImplementationClass(string $implementationClass): void
    {
        if(
            !self::validateImplementation(
                $implementationClass
            )
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid implementation class: %s. Class must implement %s',
                    $implementationClass,
                    ImmutableStringIdentifierInterface::class
                )
            );
        }
        if(
            self::validateImplementationConstructor($implementationClass) === false
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid implementation class: %s. Class must have empty constructor',
                    $implementationClass
                )
            );
        }
    }

    private static function validateImplementation(string $implementationClass): bool
    {
        return class_exists($implementationClass)
            && is_a(
                $implementationClass,
                ImmutableStringIdentifierInterface::class,
                true
            );
    }

    private static function validateImplementationConstructor(string $implementationClass): bool
    {
        try {
            return new ReflectionClass(
                    $implementationClass
                )->getConstructor()->getNumberOfParameters() === 0;
        }catch (ReflectionException) {
            return false;
        }
    }

    private static function validateGenerator()
    {

    }
}
