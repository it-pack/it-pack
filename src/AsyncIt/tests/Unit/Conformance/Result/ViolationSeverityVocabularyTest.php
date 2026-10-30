<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Result;

use ItPack\AsyncIt\Conformance\Result\ViolationSeverity;
use ItPack\AsyncIt\Conformance\Result\ViolationSeverityVocabulary;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ItPack\AsyncIt\Conformance\Result\ViolationSeverityVocabulary
 */
final class ViolationSeverityVocabularyTest extends TestCase
{
    public function testSeverityConstants(): void
    {
        self::assertSame('error', ViolationSeverityVocabulary::SEVERITY_ERROR);
        self::assertSame('warning', ViolationSeverityVocabulary::SEVERITY_WARNING);
    }

    public function testEnumCasesMatchVocabulary(): void
    {
        self::assertSame(ViolationSeverityVocabulary::SEVERITY_ERROR, ViolationSeverity::Error->value);
        self::assertSame(ViolationSeverityVocabulary::SEVERITY_WARNING, ViolationSeverity::Warning->value);
    }
}
