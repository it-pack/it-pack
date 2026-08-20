<?php

declare(strict_types=1);

namespace ItPack\DisenchantIt\ValueObject;

/**
 * Closed set of roles a detected literal can play in the source, inferred by
 * RoleClassifier from its AST parent. Genuinely closed internal state, so a
 * backed enum (per AGENTS.md) rather than a bare string — this removes the
 * typo-prone role literals previously scattered across RoleClassifier,
 * CandidateFilter and the literal carriers.
 */
enum LiteralRole: string
{
    case NumericExpression = 'numeric_expression';
    case ExceptionMessage = 'exception_message';
    case ConstructorArg = 'constructor_arg';
    case FormatString = 'format_string';
    case FunctionArg = 'function_arg';
    case MethodCallArg = 'method_call_arg';
    case CallArg = 'call_arg';
    case ArrayKeyLiteral = 'array_key_literal';
    case ArrayValueLiteral = 'array_value_literal';
    case ArrayKey = 'array_key';
    case Comparison = 'comparison';
    case BranchValue = 'branch_value';
    case EnumBackingValue = 'enum_backing_value';
    case ClassConstValue = 'class_const_value';
    case Unclassified = 'unclassified';
}
