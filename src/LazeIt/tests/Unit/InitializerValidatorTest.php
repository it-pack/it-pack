<?php

declare(strict_types=1);

namespace Tests\Unit;

use ItPack\LazeIt\SDK\InitializerValidator;

class InitializerValidatorTest extends \Codeception\Test\Unit
{
    public function testValidateReturnsFalseForZeroRequiredParameters(): void
    {
        $validator = new InitializerValidator();
        $initializer = function () {
        };

        $this->assertFalse($validator->validate($initializer));
    }

    public function testValidateReturnsTrueForObjectTypedParameter(): void
    {
        $validator = new InitializerValidator();
        $initializer = function (object $param) {
        };

        $this->assertTrue($validator->validate($initializer));
    }

    public function testValidateReturnsTrueForClassTypedParameter(): void
    {
        $validator = new InitializerValidator();
        $initializer = function (self $param) {
        };

        $this->assertTrue($validator->validate($initializer));
    }

    public function testValidateReturnsFalseForBuiltinTypeParameter(): void
    {
        $validator = new InitializerValidator();
        $initializer = function (string $param) {
        };

        $this->assertFalse($validator->validate($initializer));
    }

    public function testValidateReturnsTrueForUntypedParameter(): void
    {
        $validator = new InitializerValidator();
        $initializer = function ($param) {
        };

        $this->assertTrue($validator->validate($initializer));
    }
}
