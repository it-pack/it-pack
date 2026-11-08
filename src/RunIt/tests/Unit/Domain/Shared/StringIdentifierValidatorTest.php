<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use ItPack\RunIt\Contract\Domain\StringIdentifierValidatorInterface;
use ItPack\RunIt\Contract\Exception\InvalidValueExceptionInterface;
use ItPack\RunIt\Contract\Exception\RunItExceptionInterface;
use ItPack\RunIt\Domain\Exception\InvalidIdentifierException;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Throwable;

#[CoversClass(StringIdentifierValidator::class)]
#[CoversClass(ExecutionId::class)]
final class StringIdentifierValidatorTest extends Unit
{
    /**
     * Values every RunIt identifier must accept.
     *
     * @return array<string, array{string}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'uuid' => ['018f3a1c-6f0e-7c4a-9f2b-0c1d2e3f4a5b'],
            'ulid' => ['01ARZ3NDEKTSV4RRFFQ69G5FAV'],
            'w3c trace id' => ['4bf92f3577b34da6a3ce929d0e0e4736'],
            'vendor prefixed' => ['acme:execution/9f2b'],
            'single character' => ['a'],
            'punctuation only' => ['!~'],
            'digits' => ['1'],
        ];
    }

    /**
     * Values every RunIt identifier must reject.
     *
     * @return array<string, array{string}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'empty' => [''],
            'space' => ['exec 1'],
            'leading space' => [' exec'],
            'trailing space' => ['exec '],
            'tab' => ["exec\t1"],
            'newline' => ["exec\n1"],
            'carriage return' => ["exec\r1"],
            'null byte' => ["exec\0"],
            'escape sequence' => ["exec\x1b[31m"],
            'delete control character' => ["exec\x7f"],
            'non-ascii' => ['exécution'],
            'emoji' => ['exec-🙂'],
            'too long' => [str_repeat('a', StringIdentifierValidator::MAX_LENGTH + 1)],
        ];
    }

    #[DataProvider('acceptedProvider')]
    public function testAcceptedValuesDoNotThrow(string $value): void
    {
        try {
            new StringIdentifierValidator()->validate($value);
        } catch (InvalidIdentifierException $invalidIdentifierException) {
            self::fail(
                sprintf('Value %s was rejected: %s', bin2hex($value), $invalidIdentifierException->getMessage()),
            );
        }

        self::assertSame($value, ExecutionId::fromString($value)->toString());
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectedValuesThrowATypedRunItException(string $value): void
    {
        $caught = null;

        try {
            new StringIdentifierValidator()->validate($value);
        } catch (Throwable $throwable) {
            $caught = $throwable;
        }

        self::assertInstanceOf(InvalidIdentifierException::class, $caught);
        self::assertInstanceOf(InvalidValueExceptionInterface::class, $caught);
        self::assertInstanceOf(RunItExceptionInterface::class, $caught);
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectionMessageNeverEchoesTheRejectedValue(string $value): void
    {
        try {
            new StringIdentifierValidator()->validate($value);

            self::fail('The value should have been rejected.');
        } catch (InvalidIdentifierException $invalidIdentifierException) {
            self::assertNotSame('', $invalidIdentifierException->getMessage());

            if ($value !== '') {
                self::assertStringNotContainsString($value, $invalidIdentifierException->getMessage());
            }
        }
    }

    public function testTheMaximumLengthIsInclusive(): void
    {
        $validator = new StringIdentifierValidator();

        $validator->validate(str_repeat('a', StringIdentifierValidator::MAX_LENGTH));

        $this->expectException(InvalidIdentifierException::class);

        $validator->validate(str_repeat('a', StringIdentifierValidator::MAX_LENGTH + 1));
    }

    public function testAStricterValidatorCanReplaceTheDefaultRules(): void
    {
        $uuidOnly = new class () implements StringIdentifierValidatorInterface {
            public function validate(string $value): void
            {
                StringIdentifierValidator::assertValid($value);

                if (preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $value) !== 1) {
                    throw InvalidIdentifierException::unsupportedCharacters();
                }
            }
        };

        $uuidOnly->validate('018f3a1c-6f0e-7c4a-9f2b-0c1d2e3f4a5b');

        $this->expectException(InvalidIdentifierException::class);

        $uuidOnly->validate('01ARZ3NDEKTSV4RRFFQ69G5FAV');
    }
}
