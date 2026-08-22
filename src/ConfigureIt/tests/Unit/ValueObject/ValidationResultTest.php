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
        verify($result->warnings())->equals([]);
        verify($result->hasErrors())->false();
        verify($result->hasWarnings())->false();
    }

    public function testInvalidResultCarriesErrors(): void
    {
        $errors = [['path' => 'app.debug', 'message' => 'must be bool', 'severity' => 'error']];
        $result = new ValidationResult(false, $errors);

        verify($result->valid())->false();
        verify($result->errors())->equals($errors);
        verify($result->hasErrors())->true();
        verify($result->hasWarnings())->false();
    }

    public function testResultSplitsErrorsAndWarningsBySeverity(): void
    {
        $issues = [
            ['path' => 'app.debug', 'message' => 'must be bool', 'severity' => 'error'],
            ['path' => 'db.host', 'message' => 'deprecated', 'severity' => 'warning'],
        ];
        $result = new ValidationResult(false, $issues);

        verify($result->hasErrors())->true();
        verify($result->hasWarnings())->true();
        verify($result->issues())->equals($issues);
        verify($result->errors())->equals([$issues[0]]);
        verify($result->warnings())->equals([$issues[1]]);
    }
}
