<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Test\Unit;
use ItPack\RunIt\Behavior\Domain\StringValueValidator;
use ItPack\RunIt\Contract\Domain\StringValueValidatorInterface;
use ItPack\RunIt\Contract\Exception\InvalidStringValueException;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Execution\ExecutionName;
use Throwable;

final class StringValueValidatorTest extends Unit
{
    /**
     * Values every RunIt string value must accept.
     *
     * @return array<string, array{string}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'dotted name' => ['configuration.resolve'],
            'kind with prefix' => ['http.request'],
            'underscore' => ['cli_command'],
            'dash' => ['queue-job'],
            'single character' => ['a'],
            'digits' => ['v1'],
            'mixed separators' => ['a.b-c_d'],
        ];
    }

    /**
     * Values every RunIt string value must reject.
     *
     * @return array<string, array{string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'empty' => [''],
            'space' => ['exec 1'],
            'tab' => ["exec\t1"],
            'newline' => ["exec\n1"],
            'slash' => ['exec/1'],
            'colon' => ['exec:1'],
            'at sign' => ['exec@1'],
            'emoji' => ['exec-🙂'],
            'non-ascii' => ['exécution'],
            'too long' => [str_repeat('a', StringValueValidator::MAX_LENGTH + 1)],
        ];
    }

    /**
     * @dataProvider acceptedProvider
     */
    public function testAcceptedValuesDoNotThrow(string $value): void
    {
        try {
            new StringValueValidator()->validate($value);
        } catch (InvalidStringValueException $invalidStringValueException) {
            self::fail(
                sprintf('Value %s was rejected: %s', bin2hex($value), $invalidStringValueException->getMessage()),
            );
        }

        self::assertSame($value, ExecutionName::fromString($value)->toString());
    }

    /**
     * @dataProvider rejectedProvider
     */
    public function testRejectedValuesThrowATypedRunItException(string $value): void
    {
        $caught = null;

        try {
            new StringValueValidator()->validate($value);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertInstanceOf(InvalidStringValueException::class, $caught);
        self::assertInstanceOf(InvalidValueExceptionInterface::class, $caught);
        self::assertInstanceOf(RunItExceptionInterface::class, $caught);
    }

    /**
     * @dataProvider rejectedProvider
     */
    public function testRejectionMessageNeverEchoesTheRejectedValue(string $value): void
    {
        try {
            new StringValueValidator()->validate($value);

            self::fail('The value should have been rejected.');
        } catch (InvalidStringValueException $invalidStringValueException) {
            if ($value !== '') {
                self::assertStringNotContainsString($value, $invalidStringValueException->getMessage());
            }
        }
    }

    public function testTheMaximumLengthIsInclusive(): void
    {
        $validator = new StringValueValidator();

        $validator->validate(str_repeat('a', StringValueValidator::MAX_LENGTH));

        $this->expectException(InvalidStringValueException::class);

        $validator->validate(str_repeat('a', StringValueValidator::MAX_LENGTH + 1));
    }

    public function testTheDefaultValidatorIsUsableThroughTheContract(): void
    {
        $validator = new StringValueValidator();

        self::assertInstanceOf(StringValueValidatorInterface::class, $validator);

        $validator->validate('acceptable.name');
    }
}
