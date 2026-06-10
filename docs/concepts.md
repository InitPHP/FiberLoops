# Concepts

This page explains how FiberLoops schedules work, so the behaviour of every
method follows from one model rather than a list of rules.

## Fibers in one paragraph

A [`Fiber`](https://www.php.net/manual/en/language.fibers.php) is a function that
can **suspend** itself mid-execution and be **resumed** later, retaining its local
variables and position. Suspending hands control to whoever started or resumed the
fiber; resuming hands control back into the fiber. Only one fiber runs at a time —
fibers give you concurrency (interleaved progress), not parallelism (simultaneous
execution).

FiberLoops wraps each task in a fiber and coordinates the suspend/resume dance for
you.

## The task queue

A `Loop` owns a queue of tasks. `defer()` appends to it; `run()` consumes it.

```
defer(A)   queue: [A]
defer(B)   queue: [A, B]
run()      drive A and B until the queue is empty
```

Tasks are stored under integer keys. A task is removed from the queue the moment
it has terminated (returned).

## How `run()` advances tasks

`run()` loops until the queue is empty. On each pass it walks every task once and
**advances it by one step**:

- if the task has not started yet — **start** it (run it until its first yield or
  its return);
- if the task is suspended — **resume** it (run it until its next yield or return);
- if the task has terminated — **remove** it from the queue.

Because each task is advanced exactly once per pass, the tasks make progress in a
**round-robin** order. A "step" is the work a task does between two yields.

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (['a1', 'a2', 'a3'] as $s) { echo "$s "; $loop->next(); }
});
$loop->defer(function () use ($loop) {
    foreach (['b1', 'b2', 'b3'] as $s) { echo "$s "; $loop->next(); }
});

$loop->run();
```

```text
a1 b1 a2 b2 a3 b3
```

## Cooperative, not preemptive

The loop never interrupts a task. Control only returns to the scheduler when a
task **yields** (`next()` / `sleep()`) or **returns**. Two consequences:

- A task that never yields runs to completion before any sibling is advanced.
- A task that yields often shares the loop fairly with its siblings.

This is the central trade-off of cooperative scheduling: you get predictable,
lock-free interleaving in exchange for having to place your own yield points.

## Yielding with `next()`

Inside a task, `next()` suspends the current fiber and returns control to `run()`.
On the loop's next pass the task is resumed just after the `next()` call. Because
`next()` calls `Fiber::suspend()`, it only works **inside a fiber**; calling it
from the main script throws a [`LoopException`](api/README.md#exceptions).

## Spawning tasks at runtime

`defer()` is safe to call while the loop is running. A task added from inside
another task is not seen by the current pass (which is iterating a snapshot of the
queue) but is picked up on the next one:

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

## Where `await()` fits

`await()` runs a single task to completion and returns its value. It is a
convenience built on the same primitives: start the sub-task's fiber, resume it
until it terminates, and — when called from inside another task — yield to the
scheduler between steps so siblings keep running. See
[await & concurrency](await-and-concurrency.md).
