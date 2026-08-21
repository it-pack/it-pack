<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Validation;

use Codeception\Test\Unit;
use ItPack\CommitIt\Domain\Exception\InvalidReferenceNameException;
use ItPack\CommitIt\Domain\Validation\RefKind;
use ItPack\CommitIt\Domain\Validation\RefNameValidator;

/**
 * @covers \ItPack\CommitIt\Domain\Validation\RefNameValidator
 * @covers \ItPack\CommitIt\Domain\Validation\RefKind
 */
final class RefNameValidatorTest extends Unit
{
    public function testValidNames(): void
    {
        $valid = ['main', 'feature/login', 'release/v1.0', 'v1.0', 'origin', 'upstream/ci', 'user.name', 'a.b.c', 'x-y_z'];

        foreach ($valid as $name) {
            self::assertNull(RefNameValidator::reason($name), sprintf('Expected "%s" to be valid', $name));
            RefNameValidator::assertValid($name, RefKind::Branch);
            RefNameValidator::assertValid($name, RefKind::Tag);
            RefNameValidator::assertValid($name, RefKind::Remote);
        }
    }

    public function testInvalidNames(): void
    {
        $invalid = [
            '' => 'empty',
            '  ' => 'whitespace',
            '..' => 'double dot',
            'a..b' => 'embedded double dot',
            '/head' => 'leading slash',
            'head/' => 'trailing slash',
            'a//b' => 'double slash',
            'a b' => 'space',
            "a\tb" => 'tab',
            'a~b' => 'tilde',
            'a^b' => 'caret',
            'a:b' => 'colon',
            'a?b' => 'question',
            'a*b' => 'asterisk',
            'a[b' => 'bracket',
            'a\\b' => 'backslash',
            '@{x}' => 'at brace',
            '@' => 'lone at',
            '.hidden' => 'dot component',
            'a/b/.c' => 'dot sub-component',
            'a.lock' => 'lock suffix',
            'a/b/c.lock' => 'lock sub-suffix',
        ];

        foreach ($invalid as $name => $reason) {
            self::assertNotNull(RefNameValidator::reason($name), sprintf('Expected "%s" (%s) to be invalid', $name, $reason));
        }
    }

    public function testAssertValidThrowsTaggedException(): void
    {
        $this->expectException(InvalidReferenceNameException::class);
        $this->expectExceptionMessage('Branch name is invalid');

        RefNameValidator::assertValid('..', RefKind::Branch);
    }

    public function testAssertValidPassesForValidName(): void
    {
        RefNameValidator::assertValid('main', RefKind::Tag);

        self::assertNull(RefNameValidator::reason('main'));
    }
}
