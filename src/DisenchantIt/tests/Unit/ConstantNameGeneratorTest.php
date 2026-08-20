<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\ConstantNameGenerator;

final class ConstantNameGeneratorTest extends Unit
{
    private ConstantNameGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = ConstantNameGenerator::create();
    }

    public function testSlugifiesIdentifierToUpperSnake(): void
    {
        $this->assertSame('ADMIN_USER', $this->generator->generate('admin-user'));
        $this->assertSame('APP_KEY', $this->generator->generate('APP_KEY'));
    }

    public function testPrependsVWhenNameStartsWithDigit(): void
    {
        $this->assertSame('V_123', $this->generator->generate('123'));
    }

    public function testFallbackHintUsedForNonIdentifier(): void
    {
        $this->assertSame('SEP_SEPARATOR', $this->generator->generate('.', 'sep'));
    }

    public function testLastResortIsDeterministicCharCode(): void
    {
        $this->assertSame('CHAR_46', $this->generator->generate('.'));
    }

    public function testEmptyValueWithNullFallbackIsCharCode(): void
    {
        $this->assertSame('CHAR_', $this->generator->generate(''));
    }
}
