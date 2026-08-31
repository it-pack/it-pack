<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\Tests\Unit;

use Codeception\Test\Unit;
use ItPack\DisenchantIt\Adapter\Detection\RoleClassifier;
use ItPack\DisenchantIt\ValueObject\Literal\LiteralRole;
use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\EnumCase;

final class RoleClassifierTest extends Unit
{
    public function testNumericLiteralIsNumericExpression(): void
    {
        $classifier = new RoleClassifier();

        $this->assertSame(
            LiteralRole::NumericExpression,
            $classifier->classify(new LNumber(5), null),
        );
        $this->assertSame(
            LiteralRole::NumericExpression,
            $classifier->classify(new DNumber(3.14), null),
        );
    }

    public function testExceptionConstructorArgIsExceptionMessage(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('boom');
        $arg = new Arg($node);
        $arg->setAttribute('parent', new New_(new Name('FooException')));

        $this->assertSame(
            LiteralRole::ExceptionMessage,
            $classifier->classify($node, $arg),
        );
    }

    public function testNoiseFunctionArgIsFormatString(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('%s');

        foreach (['sprintf', 'printf', 'vsprintf', 'trigger_error'] as $fn) {
            $arg = new Arg($node);
            $arg->setAttribute('parent', new FuncCall(new Name($fn)));
            $this->assertSame(
                LiteralRole::FormatString,
                $classifier->classify($node, $arg),
                "expected format-string role for {$fn}()",
            );
        }
    }

    public function testPlainFunctionArgIsFunctionArg(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('tag');
        $arg = new Arg($node);
        $arg->setAttribute('parent', new FuncCall(new Name('createOccurrence')));

        $this->assertSame(
            LiteralRole::FunctionArg,
            $classifier->classify($node, $arg),
        );
    }

    public function testMethodAndStaticCallArgsAreMethodCallArg(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('event');
        $methodArg = new Arg($node);
        $methodArg->setAttribute('parent', new MethodCall(new Variable('x'), new Identifier('fire')));
        $this->assertSame(LiteralRole::MethodCallArg, $classifier->classify($node, $methodArg));

        $staticArg = new Arg($node);
        $staticArg->setAttribute('parent', new StaticCall(new Name('Self'), new Identifier('fire')));
        $this->assertSame(LiteralRole::MethodCallArg, $classifier->classify($node, $staticArg));
    }

    public function testUnrecognizedCallArgFallsThroughToCallArg(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('x');
        $arg = new Arg($node);
        $arg->setAttribute('parent', new Variable('notACall'));

        $this->assertSame(LiteralRole::CallArg, $classifier->classify($node, $arg));
    }

    public function testArrayKeyVersusValueLiterals(): void
    {
        $classifier = new RoleClassifier();

        $keyNode = new String_('origin');
        $valueNode = new String_('defaults');
        $item = new ArrayItem($valueNode, $keyNode);

        $this->assertSame(
            LiteralRole::ArrayKeyLiteral,
            $classifier->classify($keyNode, $item),
        );
        $this->assertSame(
            LiteralRole::ArrayValueLiteral,
            $classifier->classify($valueNode, $item),
        );
    }

    public function testArrayDimFetchIsArrayKey(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('k');
        $parent = new ArrayDimFetch(new Variable('arr'), $node);

        $this->assertSame(LiteralRole::ArrayKey, $classifier->classify($node, $parent));
    }

    public function testComparisonIsComparison(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('a');
        $parent = new Identical(new Variable('x'), new Variable('y'));

        $this->assertSame(LiteralRole::Comparison, $classifier->classify($node, $parent));

        $equalParent = new Equal(new Variable('x'), new Variable('y'));
        $this->assertSame(LiteralRole::Comparison, $classifier->classify($node, $equalParent));
    }

    public function testBranchValueForMatchAndCase(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('v');

        $this->assertSame(
            LiteralRole::BranchValue,
            $classifier->classify($node, new Match_(new Variable('x'))),
        );
        $this->assertSame(
            LiteralRole::BranchValue,
            $classifier->classify($node, new Case_(null, [])),
        );
    }

    public function testEnumBackingValueIsEnumBackingValue(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('active');
        $parent = new EnumCase(new Identifier('Status'), $node);

        $this->assertSame(LiteralRole::EnumBackingValue, $classifier->classify($node, $parent));
    }

    public function testClassConstValueIsExcludedForIdempotency(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('path');
        $parent = new Const_(new Identifier('PATH'), $node);

        $this->assertSame(LiteralRole::ClassConstValue, $classifier->classify($node, $parent));
    }

    public function testUnclassifiedByDefault(): void
    {
        $classifier = new RoleClassifier();
        $node = new String_('loose');

        $this->assertSame(LiteralRole::Unclassified, $classifier->classify($node, null));
    }

    public function testLooksIdentifierLikeAcceptsTokens(): void
    {
        $classifier = new RoleClassifier();

        $this->assertTrue($classifier->looksIdentifierLike('json'));
        $this->assertTrue($classifier->looksIdentifierLike('admin-user'));
        $this->assertTrue($classifier->looksIdentifierLike('APP_KEY'));
        $this->assertTrue($classifier->looksIdentifierLike('a/b'));
    }

    public function testLooksIdentifierLikeRejectsProse(): void
    {
        $classifier = new RoleClassifier();

        $this->assertFalse($classifier->looksIdentifierLike(''));
        $this->assertFalse($classifier->looksIdentifierLike('has space'));
        $this->assertFalse($classifier->looksIdentifierLike('weird$'));
        $this->assertFalse($classifier->looksIdentifierLike('a,b'));
        $this->assertFalse($classifier->looksIdentifierLike('end!'));
    }
}
