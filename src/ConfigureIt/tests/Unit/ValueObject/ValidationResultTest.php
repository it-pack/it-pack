<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use Codeception\Test\Unit;
use ItPack\ConfigureIt\ValueObject\ValidationResult;

class ValidationResultTest extends Unit
{
    public function testValidResultHasNoErrors(): void
    {
        $result = new ValidationResult(true);

        verify($result->valid())->true();
        verify($result->errors())->equals([]);
    }

    public function testInvalidResultCarriesErrors(): void
    {
        $errors = [['path' => 'app.debug', 'message' => 'must be bool']];
        $result = new ValidationResult(false, $errors);

        verify($result->valid())->false();
        verify($result->errors())->equals($errors);
    }
}
