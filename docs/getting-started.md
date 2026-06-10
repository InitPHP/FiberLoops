# Getting started

This guide takes you from an empty project to two tasks running concurrently on a
single thread.

## Install

```bash
composer require initphp/fiber-loops
```

FiberLoops needs **PHP 8.1 or later** and nothing else — fibers are a core
language feature, so there is no extension to install.

## The mental model

A `Loop` holds a queue of **tasks**. Each task is a PHP
[`Fiber`](https://www.php.net/manual/en/language.fibers.php): a function that can
pause itself and be resumed later, keeping its local state across the pause.

You do two things:

1. **`defer()`** a task to add it to the queue.
2. **`run()`** the loop to drive every task to completion.

While a task runs, it can call **`next()`** to hand control back to the loop. The
loop then advances the other tasks and, on its next pass, resumes the task right
where it paused. This is *cooperative* scheduling: tasks choose when to yield.

## Your first loop

```php
require 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;

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

Read the output top to bottom: task-1 prints `a`, yields; task-2 prints `x`,
yields; task-1 resumes and prints `b`, and so on. The two tasks take turns.

## What happens without `next()`?

`next()` is the only reason the tasks interleave. Remove it and the first task
holds the loop until it finishes:

```php
$loop->defer(function () {
    foreach (['a', 'b', 'c'] as $step) {
        echo "task-1: $step\n";       // no next() -> runs straight through
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

There is no preemption: a task that never yields runs to completion before any
sibling is touched. Long-running tasks must call `next()` periodically to stay a
good citizen.

## Pausing with `sleep()`

`sleep()` lets a task step aside for a while without blocking the others:

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

The first task sleeps while the second runs to completion, then the first wakes
up and finishes. Note that `sleep()` is a busy-wait — see
[Caveats](caveats.md#sleep-is-a-busy-wait).

## Next steps

- [Concepts](concepts.md) — how the scheduler works under the hood.
- [await & concurrency](await-and-concurrency.md) — run a sub-task and get its result.
- [API reference](api/README.md) — the precise contract of every method.
