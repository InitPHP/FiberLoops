# Core Concepts

FiberLoops is small enough that one mental model explains every method. This page
builds that model; the rest of the wiki is detail on top of it.

## Fibers, in one paragraph

A [`Fiber`](https://www.php.net/manual/en/language.fibers.php) is a function that
can **suspend** itself partway through and be **resumed** later, keeping its local
variables and its position. Suspending hands control to whoever started or resumed
the fiber; resuming hands control back into it. Crucially, **only one fiber runs at
a time** — fibers give you *concurrency* (interleaved progress), not *parallelism*
(simultaneous execution). FiberLoops wraps each task in a fiber and coordinates the
suspend/resume dance for you.

## A task is a fiber; the loop is a queue

A `Loop` owns a queue of tasks. You append to it with `defer()` and drain it with
`run()`.

```
 defer(A)      queue: [ A ]
 defer(B)      queue: [ A, B ]
 run()         advance A and B, round-robin, until the queue is empty
```

Internally the queue is a plain array keyed by an integer id. A task is removed the
moment it has **terminated** (returned).

## How `run()` advances tasks

`run()` loops until the queue is empty. On each **pass** it walks every task once
and advances it by exactly one step:

```
for each task in the queue:
    not started?   -> start it   (run until its first yield or its return)
    suspended?     -> resume it  (run until its next  yield or its return)
    terminated?    -> remove it from the queue
```

Because each task is advanced once per pass, the tasks make progress in
**round-robin** order. A "step" is the work a task does between two yields.

```php
require_once 'vendor/autoload.php';
use InitPHP\FiberLoops\Loop;

$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (['a1', 'a2', 'a3'] as $s) { echo "$s "; $loop->next(); }
});
$loop->defer(function () use ($loop) {
    foreach (['b1', 'b2', 'b3'] as $s) { echo "$s "; $loop->next(); }
});

$loop->run();
echo "\n";
```

```text
a1 b1 a2 b2 a3 b3
```

Read it as: start A (`a1`), start B (`b1`), resume A (`a2`), resume B (`b2`)…

## Cooperative, not preemptive

The loop **never interrupts** a task. Control returns to the scheduler only when a
task **yields** (`next()` / `sleep()`) or **returns**. Two consequences follow,
and they are the whole trade-off of the model:

- A task that **never yields** runs to completion before any sibling is advanced.
- A task that **yields often** shares the loop fairly with its siblings.

You place the yield points. In exchange you get predictable, lock-free
interleaving — there are no data races between tasks, because only one runs at a
time and switches happen only where you wrote `next()`.

## Yielding with `next()`

Inside a task, `next()` calls `Fiber::suspend()` under the hood: it suspends the
current fiber and returns control to `run()`. On the loop's next pass the task is
resumed on the line *after* the `next()` call. Because it suspends a fiber, it is
**only valid inside a fiber** — calling it from the main script throws a
[`LoopException`](Error-Handling.md). See [Yielding & Sleeping](Yielding-and-Sleeping.md).

## Spawning tasks at runtime

`defer()` is safe to call while the loop is running. The current pass iterates a
snapshot of the queue, so a task you add from inside another task is not seen by
this pass — but it is picked up on the next one:

```php
require_once 'vendor/autoload.php';
use InitPHP\FiberLoops\Loop;

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

`outer` finishes its current step (`start`), the new task is queued, `outer`
resumes and finishes (`end`), and only then does the spawned task get its first
pass.

## Where `await()` fits

`await()` is a convenience built on these same primitives: it starts a sub-task's
fiber, resumes it until it terminates, and — when called from inside another task
— yields to the scheduler between the sub-task's steps so siblings keep running.
It then returns the sub-task's value. See [Awaiting Results](Awaiting-Results.md).

## The whole API in one glance

| Method | One-line meaning |
| ------ | ---------------- |
| `defer($task)` | Add a task (callable or `Fiber`) to the queue. |
| `run()` | Drive every task to completion. |
| `next($value = null)` | Yield from the current task back to the loop. |
| `sleep($seconds)` | Cooperatively pause the current task. |
| `await($task)` | Run a task to completion and return its value. |

Every one of these is detailed in the [API Reference](API-Reference.md).
