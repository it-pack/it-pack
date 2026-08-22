<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\ContentClassifier;
use ItPack\DisenchantIt\Contract\ContentClassifierInterface;
use ItPack\DisenchantIt\ValueObject\LiteralKind;
use ItPack\DisenchantIt\ValueObject\LiteralRole;
use ItPack\DisenchantIt\ValueObject\LiteralSubkind;
use ItPack\DisenchantIt\ValueObject\NumberLiteral;
use ItPack\DisenchantIt\ValueObject\StringLiteral;

final class ContentClassifierTest extends Unit
{
    private ContentClassifierInterface $classifier;

    protected function setUp(): void
    {
        $this->classifier = new ContentClassifier();
    }

    public function stringCases(): iterable
    {
        yield 'absolute url' => ['https://example.com/path', LiteralSubkind::Url];
        yield 'protocol-relative url' => ['//cdn.example.com', LiteralSubkind::Url];
        yield 'path-like url' => ['/foo/bar', LiteralSubkind::Url];
        yield 'slash-delimited regex' => ['/^[a-z]+$/', LiteralSubkind::Regex];
        yield 'hash-delimited regex' => ['#^foo$#', LiteralSubkind::Regex];
        yield 'tilde-delimited regex' => ['~foo~', LiteralSubkind::Regex];
        yield 'json object' => ['{"a":1}', LiteralSubkind::Json];
        yield 'json array' => ['[1,2,3]', LiteralSubkind::Json];
        yield 'sql select' => ['SELECT * FROM t', LiteralSubkind::Sql];
        yield 'sql insert' => ['insert into t values (1)', LiteralSubkind::Sql];
        yield 'semver' => ['1.2.3', LiteralSubkind::Version];
        yield 'two-part version' => ['2.0', LiteralSubkind::Version];
    }

    /**
     * @dataProvider stringCases
     */
    public function testClassifiesStringSubkinds(string $value, LiteralSubkind $expected): void
    {
        $lit = new StringLiteral(
            value: $value,
            line: 1,
            role: LiteralRole::Unclassified,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame($expected, $this->classifier->classify($lit));
    }

    public function testRegexWithInternalSpaceIsNotRegex(): void
    {
        $lit = new StringLiteral('a b', 1, LiteralRole::Unclassified, 'C', 'm');

        $this->assertSame(LiteralSubkind::Unknown, $this->classifier->classify($lit));
    }

    public function testPlainProseFallsBackToUnknown(): void
    {
        $lit = new StringLiteral('hello world', 1, LiteralRole::Unclassified, 'C', 'm');

        $this->assertSame(LiteralSubkind::Unknown, $this->classifier->classify($lit));
    }

    public function testStatusCodeWhenIntInComparisonContext(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '200',
            raw: '200',
            line: 1,
            role: LiteralRole::Comparison,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame(LiteralSubkind::StatusCode, $this->classifier->classify($lit));
    }

    public function testIntOutsideStatusCodeRangeIsUnknown(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '42',
            raw: '42',
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame(LiteralSubkind::Unknown, $this->classifier->classify($lit));
    }

    public function testFloatIsUnknown(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Float->value,
            value: '3.14',
            raw: '3.14',
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame(LiteralSubkind::Unknown, $this->classifier->classify($lit));
    }

    /**
     * @dataProvider bitmaskCases
     */
    public function testClassifiesBitmaskForPowersOfTwoInBitwiseContext(int $value): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: (string) $value,
            raw: (string) $value,
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame(LiteralSubkind::Bitmask, $this->classifier->classify($lit, true));
    }

    public function bitmaskCases(): iterable
    {
        yield '1' => [1];
        yield '2' => [2];
        yield '4' => [4];
        yield '8' => [8];
        yield '1024' => [1024];
    }

    public function testDoesNotClassifyBitmaskWithoutBitwiseContext(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '4',
            raw: '4',
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame(LiteralSubkind::Unknown, $this->classifier->classify($lit, false));
    }

    public function testDoesNotClassifyBitmaskForNonPowerOfTwo(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '3',
            raw: '3',
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame(LiteralSubkind::Unknown, $this->classifier->classify($lit, true));
    }

    public function testDoesNotClassifyBitmaskForLoneHundred(): void
    {
        $lit = new NumberLiteral(
            kind: LiteralKind::Int->value,
            value: '100',
            raw: '100',
            line: 1,
            role: LiteralRole::NumericExpression,
            className: 'C',
            methodName: 'm',
        );

        $this->assertSame(LiteralSubkind::Unknown, $this->classifier->classify($lit, true));
    }
}
