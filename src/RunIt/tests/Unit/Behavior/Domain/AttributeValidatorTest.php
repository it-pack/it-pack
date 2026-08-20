<?php

declare(strict_types=1);

namespace Tests\Unit\Behavior\Domain;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\AttributeValidator;
use ItPack\RunIt\Contract\Exception\InvalidAttributeException;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use stdClass;

/**
 * @group behavior
 * @group security
 */
final class AttributeValidatorTest extends Unit
{
    public function testDefaultRejectsTooManyAttributes(): void
    {
        $attributes = [];
        for ($i = 0; $i < 65; ++$i) {
            $attributes['k' . $i] = 1;
        }

        $this->expectException(InvalidAttributeException::class);
        $this->expectExceptionMessageMatches('/attribute count/i');

        new AttributeValidator()->assertValidAttributes($attributes);
    }

    public function testTightenedCountLimitIsEnforced(): void
    {
        $limits = new SecurityLimits(maxAttributeCount: 2);

        $this->expectException(InvalidAttributeException::class);

        new AttributeValidator($limits)->assertValidAttributes(['a' => 1, 'b' => 2, 'c' => 3]);
    }

    public function testOversizedScalarValueIsRejected(): void
    {
        $limits = new SecurityLimits(maxAttributeValueBytes: 3);

        $this->expectException(InvalidAttributeException::class);
        $this->expectExceptionMessageMatches('/value must not exceed/i');

        new AttributeValidator($limits)->assertValidAttributes(['note' => 'abcd']);
    }

    public function testExcessiveNestingDepthIsRejected(): void
    {
        $limits = new SecurityLimits(maxAttributeDepth: 1);

        $this->expectException(InvalidAttributeException::class);
        $this->expectExceptionMessageMatches('/nesting must not exceed/i');

        new AttributeValidator($limits)->assertValidAttributes(['list' => [['x' => 1]]]);
    }

    public function testObjectsAreRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        new AttributeValidator()->assertValidAttributes(['obj' => new stdClass()]);
    }

    public function testResourcesAreRejected(): void
    {
        $this->expectException(InvalidAttributeException::class);

        new AttributeValidator()->assertValidAttributes(['stream' => fopen('php://memory', 'rb')]);
    }

    public function testOversizedKeyIsRejected(): void
    {
        $limits = new SecurityLimits(maxAttributeKeyBytes: 3);

        $this->expectException(InvalidAttributeException::class);

        new AttributeValidator($limits)->assertValidKey('toolongkey');
    }

    public function testValidBoundedAttributesPass(): void
    {
        $limits = new SecurityLimits(maxAttributeCount: 2, maxAttributeValueBytes: 4, maxAttributeDepth: 2);

        new AttributeValidator($limits)->assertValidAttributes([
            'a' => 'ok',
            'b' => ['nested' => 'fine'],
        ]);
    }
}
