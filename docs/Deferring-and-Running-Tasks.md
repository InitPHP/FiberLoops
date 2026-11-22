# Deferring & Running Tasks

`defer()` and `run()` are the two halves of using a loop: one queues work, the
other executes it. All examples assume:

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;
```

## `defer()` — queue a task

```php
public function defer(callable|Fiber $task): void
```

`defer()` adds a task to the loop's queue. It accepts either:

- a **callable**, which is wrapped in a new `Fiber` for you, or
- an existing **`Fiber`** instance, which is queued as-is.

```php
$loop = new Loop();

// A callable (the common case):
$loop->defer(function () {
    echo "from a callable\n";
});

// A Fiber you built yourself:
$loop->defer(new Fiber(function () {
    echo "from a fiber\n";
}));

$loop->run();
```

```text
from a callable
from a fiber
```

### Tasks receive no arguments

A deferred callable is invoked with **no arguments**. If your task needs data,
capture it with `use`:

```php
$loop = new Loop();

$name = 'Ada';
$loop->defer(function () use ($name) {
    echo "hello, $name\n";
});

$loop->run();
```

```text
hello, Ada
```

### Sharing the loop with a task

Tasks almost always need the loop itself, to call `next()`, `sleep()` or
`await()`. Capture it with `use ($loop)`:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    echo "step 1\n";
    $loop->next();
    echo "step 2\n";
});

$loop->run();
```

```text
step 1
step 2
```

## `run()` — execute the queue

```php
public function run(): void
```

`run()` drives every queued task to completion. It **blocks** until the queue is
empty, advancing each task one step per pass (see
[Core Concepts](Core-Concepts.md#how-run-advances-tasks)).

### Running an empty loop is a no-op

```php
$loop = new Loop();
$loop->run();   // returns immediately, does nothing
```

### Order across tasks

Tasks start in the order they were deferred, and advance round-robin. A short task
simply finishes and drops out while longer tasks keep going:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    echo "short\n";
});

$loop->defer(function () use ($loop) {
    foreach (range(1, 3) as $n) {
        echo "long $n\n";
        $loop->next();
    }
});

$loop->run();
```

```text
short
long 1
long 2
long 3
```

On the first pass `short` runs to the end (it never yields) and `long` prints its
first line; from then on only `long` remains.

## Spawning tasks while the loop runs

`defer()` works during `run()`. A task added from inside another task is picked up
on the next pass, so tasks can spawn more tasks:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    echo "outer: start\n";
    $loop->defer(function () {
        echo "spawned: hello\n";
    });
    $loop->next();
    echo "outer: end\n";
});

$loop->run();
```

```text
outer: start
outer: end
spawned: hello
```

This is the building block for dynamic workloads — a dispatcher task that reads
jobs and `defer()`s a worker task for each one, for example.

> **A task that never returns blocks the loop.** `run()` only finishes when the
> queue drains, so every task must eventually `return`. For long-lived tasks, give
> them an exit condition (a shared flag, a counter) — see
> [Recipes → Graceful shutdown](Recipes.md#graceful-shutdown-with-a-shared-flag).

## See also

- [Yielding & Sleeping](Yielding-and-Sleeping.md) — `next()` and `sleep()`.
- [Awaiting Results](Awaiting-Results.md) — `await()`.
- [Caveats & Limitations](Caveats-and-Limitations.md) — what to watch out for.
