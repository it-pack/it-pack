<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Context;

use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Exception\InvalidContextValueException;
use ItPack\RunIt\Domain\Context\ContextValueValidator;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * T0.3 (REVIEW S3): context value recursion must be depth-bounded so a deeply
 * nested payload throws instead of overflowing the stack.
 */
final class ContextValueValidatorTest extends Unit
{
    /**
     * @return array<mixed>
     */
    private function nestedArray(int $depth): array
    {
        $value = ['leaf'];

        for ($i = 1; $i < $depth; $i++) {
            $value = [$value];
        }

        return $value;
    }

    public function testScalarValueIsAccepted(): void
    {
        new ContextValueValidator()->assertSupportedValue('ok');

        self::expectNotToPerformAssertions();
    }

    public function testObjectIsRejected(): void
    {
        $this->expectException(InvalidContextValueException::class);

        new ContextValueValidator()->assertSupportedValue(new \stdClass());
    }

    public function testShallowNestingIsAccepted(): void
    {
        new ContextValueValidator()->assertSupportedValue($this->nestedArray(7));

        self::expectNotToPerformAssertions();
    }

    public function testDeepNestingIsRejectedWithoutSegfault(): void
    {
        $this->expectException(InvalidContextValueException::class);
        $this->expectExceptionMessageMatches('/levels/i');

        new ContextValueValidator()->assertSupportedValue($this->nestedArray(64));
    }

    public function testDepthBoundIsConfigurable(): void
    {
        $validator = new ContextValueValidator(new SecurityLimits(maxAttributeDepth: 2));

        $validator->assertSupportedValue($this->nestedArray(1));

        $this->expectException(InvalidContextValueException::class);

        $validator->assertSupportedValue($this->nestedArray(2));
    }
}
