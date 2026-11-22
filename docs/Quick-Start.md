# Quick Start

This walkthrough builds your understanding one step at a time: two tasks taking
turns, what happens without yielding, pausing a task, and getting a value back
from a task. Each block is a complete script — prepend these two lines:

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;
```

## 1. Run two tasks that take turns

You add work with **`defer()`** and run it with **`run()`**. Inside a task,
**`next()`** hands control back to the loop so other tasks can advance.

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (['a', 'b', 'c'] as $step) {
        echo "task-1: $step\n";
        $loop->next();
    }
});

$loop->defer(function () use ($loop) {
    foreach (['x', 'y', 'z'] as $step) {
        echo "task-2: $step\n";
        $loop->next();
    }
});

$loop->run();
```

```text
task-1: a
task-2: x
task-1: b
task-2: y
task-1: c
task-2: z
```

Each task prints one line, yields, and the loop moves on to the other. They
interleave one step at a time.

## 2. See what `next()` actually does

`next()` is the *only* reason the tasks interleave. Take it out and the first task
runs to the end before the second one even starts:

```php
$loop = new Loop();

$loop->defer(function () {
    foreach (['a', 'b', 'c'] as $step) {
        echo "task-1: $step\n";       // no next() — runs straight through
    }
});

$loop->defer(function () {
    foreach (['x', 'y', 'z'] as $step) {
        echo "task-2: $step\n";
    }
});

$loop->run();
```

```text
task-1: a
task-1: b
task-1: c
task-2: x
task-2: y
task-2: z
```

This is the heart of *cooperative* scheduling: the loop never interrupts a task —
the task decides when to step aside. (More in [Core Concepts](Core-Concepts.md).)

## 3. Pause a task with `sleep()`

`sleep()` steps a task aside for a while without freezing the others:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    $loop->sleep(0.2);
    foreach (range(0, 5) as $value) {
        echo $value . PHP_EOL;
    }
});

$loop->defer(function () use ($loop) {
    foreach (range(6, 9) as $value) {
        echo $value . PHP_EOL;
    }
});

$loop->run();
```

```text
6
7
8
9
0
1
2
3
4
5
```

The second task finishes first because the first one is sleeping. Note that
`sleep()` is a *busy-wait*, not an OS sleep — see
[Yielding & Sleeping](Yielding-and-Sleeping.md#sleep).

## 4. Get a value back with `await()`

`await()` runs a task to completion and returns its value:

```php
$loop = new Loop();

$user = $loop->await(function () use ($loop) {
    $loop->next();                  // imagine some I/O happening here
    return ['id' => 42, 'name' => 'Ada'];
});

echo "user: {$user['id']} / {$user['name']}\n";
```

```text
user: 42 / Ada
```

Called from *inside* a task, `await()` lets the loop's other tasks keep running
while it waits — see [Awaiting Results](Awaiting-Results.md).

## Where to go next

- [Core Concepts](Core-Concepts.md) — the scheduling model in full.
- [Deferring & Running Tasks](Deferring-and-Running-Tasks.md) — `defer()` and `run()` in depth.
- [Recipes](Recipes.md) — producer/consumer, graceful shutdown, collecting results, and more.
