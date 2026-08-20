<?php

namespace _Fixture;

interface SelfStaticParentReturnClassInterface
{
	function withSelf(): SelfStaticParentReturnClassInterface;


	function withStatic(): SelfStaticParentReturnClassInterface;


	function toParent(): ConcreteChildClassInterface;


	function abstractMethod(): string;


	function childOnlyMethod(): void;


	function concreteBaseMethod(): void;
}
