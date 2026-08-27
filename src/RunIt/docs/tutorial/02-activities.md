# Tutorial 2 — Activities

**Goal:** decompose an execution into bounded units of work called **activities**, and
let RunIt track which activity each artifact belongs to.

## Why activities?

An execution is the whole operation ("place an order"). An **activity** is one step
inside it ("validate", "charge"). Activities give your measurements, observations, and
occurrences a precise home, and they can nest.

## Running an activity

Inside the execution callable you receive a **scope**. Its `activities()` runner starts
a child activity:

```php
use ItPack\RunIt\Domain\Activity\ActivityDefinition;
use ItPack\RunIt\Domain\Activity\ActivityKind;
use ItPack\RunIt\Domain\Activity\ActivityName;

$runtime->run(
    ExecutionDefinition::create(
        ExecutionName::fromString('order.place'),
        ExecutionKind::fromString('operation'),
    ),
    static function (\ItPack\RunIt\Contract\Application\ExecutionScopeInterface $scope): void {
        $scope->activities()->run(
            ActivityDefinition::create(
                ActivityName::fromString('validate'),
                ActivityKind::fromString('step'),
            ),
            static function (): void {
                echo "  validating\n";
            },
        );

        $scope->activities()->run(
            ActivityDefinition::create(
                ActivityName::fromString('charge'),
                ActivityKind::fromString('step'),
            ),
            static function (): void {
                echo "  charging\n";
            },
        );
    },
);
```

## Nesting activities

An activity can start its own children. The scope always knows the *currently active*
activity, so anything you record is bound to the right place automatically:

```php
$scope->activities()->run(
    ActivityDefinition::create(ActivityName::fromString('validate'), ActivityKind::fromString('step')),
    static function () use ($scope): void {
        $scope->activities()->run(
            ActivityDefinition::create(ActivityName::fromString('validate.items'), ActivityKind::fromString('step')),
            static function (): void {
                echo "    validating items\n";
            },
        );
    },
);
```

RunIt links each child to its parent by identity — you never manage that wiring
yourself.

> **Run this:** [`examples/nested-activities.php`](https://github.com/it-pack/run-it/blob/main/examples/nested-activities.php).

## What you learned

- Activities are bounded steps; the scope's `activities()->run()` starts one.
- Active-activity tracking means recorders bind artifacts to the right activity.
- Nesting is by parent/child identity, managed by RunIt.

## Next

[Part 3 — Measurements & observations](03-measure-and-observe.md): quantify and
describe the work.
