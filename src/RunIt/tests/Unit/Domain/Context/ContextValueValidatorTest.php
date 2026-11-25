<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ContextValueValidator::class)]
final class ContextValueValidatorTest extends Unit
{
    public function testValidKeyPasses(): void
    {
        $validator = new ContextValueValidator();

        $validator->assertValidKey('tenant.id');
        $validator->assertValidKey('my-key');
        $validator->assertValidKey('simple');
    }

    public function testEmptyKeyThrows(): void
    {
        $validator = new ContextValueValidator();

        try {
            $validator->assertValidKey('');
            self::fail('Expected InvalidContextValueException');
        } catch (InvalidContextValueException $e) {
            self::assertInstanceOf(InvalidContextValueException::class, $e);
        }
    }

    public function testInvalidKeyCharactersThrow(): void
    {
        $validator = new ContextValueValidator();

        try {
            $validator->assertValidKey('bad key!');
            self::fail('Expected InvalidContextValueException');
        } catch (InvalidContextValueException $e) {
            self::assertInstanceOf(InvalidContextValueException::class, $e);
        }
    }

    public function testSupportedValuesPass(): void
    {
        $validator = new ContextValueValidator();

        $validator->assertSupportedValue('string');
        $validator->assertSupportedValue(42);
        $validator->assertSupportedValue(3.14);
        $validator->assertSupportedValue(true);
        $validator->assertSupportedValue(null);
        $validator->assertSupportedValue(['a', 'b']);
        $validator->assertSupportedValue(['key' => 'value']);
    }

    public function testUnsupportedValueThrows(): void
    {
        $validator = new ContextValueValidator();

        try {
            $validator->assertSupportedValue(new \stdClass());
            self::fail('Expected InvalidContextValueException');
        } catch (InvalidContextValueException $e) {
            self::assertInstanceOf(InvalidContextValueException::class, $e);
        }
    }
}
