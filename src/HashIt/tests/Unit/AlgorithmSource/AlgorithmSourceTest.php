<?php

declare(strict_types=1);

namespace Tests\Unit\AlgorithmSource;

use Codeception\Test\Unit;
use InvalidArgumentException;
use ItPack\HashIt\Adapter\NativeHashIt;
use ItPack\HashIt\AlgorithmSource\CompositeAlgorithmSource;
use ItPack\HashIt\AlgorithmSource\NativeAlgorithmSource;
use ItPack\HashIt\AlgorithmSourceInterface;
use ItPack\HashIt\HashAlgorithm;

class AlgorithmSourceTest extends Unit
{
    public function testNativeReturnsEveryEnumCase(): void
    {
        $source = new NativeAlgorithmSource();

        verify($source->algorithms())->equals(HashAlgorithm::cases());
    }

    public function testNativeSupportsValueAndName(): void
    {
        $source = new NativeAlgorithmSource();

        verify($source->supports('sha256'))->true();
        verify($source->supports('SHA256'))->true();
        verify($source->supports('bogus'))->false();
    }

    public function testNativeResolvesByValueAndName(): void
    {
        $source = new NativeAlgorithmSource();

        verify($source->resolve('sha256'))->same(HashAlgorithm::SHA256);
        verify($source->resolve('SHA256'))->same(HashAlgorithm::SHA256);
    }

    public function testNativeResolveThrowsForUnknown(): void
    {
        $source = new NativeAlgorithmSource();

        $this->expectException(InvalidArgumentException::class);
        $source->resolve('does-not-exist');
    }

    public function testCompositeMergesAndDedups(): void
    {
        $composite = new CompositeAlgorithmSource([
            new NativeAlgorithmSource(),
            new NativeAlgorithmSource(),
        ]);

        verify($composite->algorithms())->equals(HashAlgorithm::cases());
    }

    public function testCompositeSupportsDelegates(): void
    {
        $composite = new CompositeAlgorithmSource([new NativeAlgorithmSource()]);

        verify($composite->supports('sha512'))->true();
        verify($composite->supports('nope'))->false();
    }

    public function testCompositeResolveDelegates(): void
    {
        $composite = new CompositeAlgorithmSource([new NativeAlgorithmSource()]);

        verify($composite->resolve('sha512'))->same(HashAlgorithm::SHA512);
    }

    public function testCompositeResolveThrowsForUnknown(): void
    {
        $composite = new CompositeAlgorithmSource([new NativeAlgorithmSource()]);

        $this->expectException(InvalidArgumentException::class);
        $composite->resolve('nope');
    }

    public function testHasherExposesDefaultSource(): void
    {
        $hasher = NativeHashIt::create();

        verify($hasher->algorithms())->equals(HashAlgorithm::cases());
        verify($hasher->supports('sha256'))->true();
        verify($hasher->resolve('sha256'))->same(HashAlgorithm::SHA256);
    }

    public function testKeyedHasherExposesDefaultSource(): void
    {
        $hasher = NativeHashIt::keyedHasher();

        verify($hasher->algorithms())->equals(HashAlgorithm::cases());
        verify($hasher->supports('sha512/256'))->true();
        verify($hasher->resolve('sha512/256'))->same(HashAlgorithm::SHA512_256);
    }

    public function testHmacHasherExposesDefaultSource(): void
    {
        $hasher = NativeHashIt::hmacHasher();

        verify($hasher->algorithms())->equals(HashAlgorithm::cases());
        verify($hasher->supports('sha384'))->true();
        verify($hasher->resolve('sha384'))->same(HashAlgorithm::SHA384);
    }

    public function testCustomSourceIsExtensibleViaCompositionRoot(): void
    {
        $custom = new class () implements AlgorithmSourceInterface {
            public function algorithms(): array
            {
                return [HashAlgorithm::SHA256, HashAlgorithm::SHA512];
            }

            public function supports(string $identifier): bool
            {
                return $identifier === 'sha256' || $identifier === 'sha512';
            }

            public function resolve(string $identifier): HashAlgorithm
            {
                return match ($identifier) {
                    'sha256' => HashAlgorithm::SHA256,
                    'sha512' => HashAlgorithm::SHA512,
                    default => throw new InvalidArgumentException('Unknown: ' . $identifier),
                };
            }
        };

        $hasher = NativeHashIt::create(null, $custom);

        verify($hasher->algorithms())->equals([HashAlgorithm::SHA256, HashAlgorithm::SHA512]);
        verify($hasher->supports('sha256'))->true();
        verify($hasher->supports('md5'))->false();
        verify($hasher->resolve('sha512'))->same(HashAlgorithm::SHA512);
    }
}
