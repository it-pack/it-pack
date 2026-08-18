<?php

namespace _Fixture;

interface SelfStaticParentReturnClassInterface
{
	function withSelf(): SelfStaticParentReturnClass;


	function withStatic(): static;


	function toParent(): ConcreteChildClass;


	function abstractMethod(): string;


	function childOnlyMethod(): void;


	function concreteBaseMethod(): void;
}
