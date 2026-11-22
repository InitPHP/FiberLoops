# Caveats & Limitations

FiberLoops is a small, sharp primitive. Knowing its edges keeps you out of
trouble. All examples assume `use InitPHP\FiberLoops\Loop;`.

## Scheduling is cooperative, not preemptive

Nothing interrupts a running task. Control returns to the loop only when a task
**yields** (`next()` / `sleep()`) or **returns**. A task that never yields starves
every other task until it finishes:

```php
$loop->defer(function () {
    while (true) {
        // never yields — the loop and every other task are now frozen
    }
});
```

Put a `next()` inside any long-running or unbounded loop so siblings get a turn.

## `sleep()` is a busy-wait

`sleep()` does **not** put the process to sleep. It spins, calling `next()` on each
iteration until the deadline passes. Consequences:

- **Siblings keep running** while one task sleeps — that is the point.
- **The CPU stays busy.** If *every* task is sleeping, the loop spins at 100% CPU
  re-checking the clock. FiberLoops has no idle/poll phase.
- `sleep()` guarantees *at least* the requested duration, not an exact one — the
  actual pause depends on how often the loop comes back around.

If you need genuinely idle waiting on timers or I/O (sockets, streams, signals),
FiberLoops is the wrong tool on its own — reach for a full async runtime such as
[ReactPHP](https://reactphp.org) or [Amp](https://amphp.org), or build a real
reactor *on top of* these primitives.

## `sleep(0)` is a pure no-op

Because the busy-wait condition is immediately false, `sleep(0)` (or any
non-positive value) returns **without yielding even once**. If you want "yield one
turn," call `next()` directly — do not rely on `sleep(0)` to do it.

## `next()` and `sleep()` must run inside a fiber

Both call `Fiber::suspend()`, which is only legal inside a fiber. Calling them from
the main script throws a
[`LoopException`](Error-Handling.md#calling-next-or-sleep-outside-a-fiber). Only call
them from inside a task you passed to `defer()` or `await()`. (`sleep(0)` is the
exception — it never reaches the suspend, so it is safe anywhere.)

## Concurrency, not parallelism

Fibers interleave on a single thread; they do not run on multiple cores at once.
FiberLoops is ideal for overlapping tasks that *yield* (cooperative pipelines,
step-wise state machines, generators), not for speeding up CPU-bound work. If a
task is pure number-crunching with no natural yield points, fibers will not make it
faster.

## `run()` returns only when the queue drains

`run()` blocks until every task has terminated. There is no built-in "stop the
loop" call. If you need to stop early, give your tasks their own exit condition (a
shared flag, a max-iteration counter) and have them `return` — see
[Recipes → Graceful shutdown](Recipes.md#graceful-shutdown-with-a-shared-flag).

## A task that never returns blocks the loop forever

The flip side of the above: every task must eventually return. An infinite task
with no exit condition means `run()` never finishes.

## An uncaught exception breaks out of `run()`

A task is a fiber; an uncaught exception inside it propagates out of `run()` (or
`await()`), and tasks queued after it on that pass may not run. Wrap risky work in
a `try/catch` inside the task if you want isolation. See
[Error Handling](Error-Handling.md#exceptions-thrown-inside-a-task).

## No timers, streams, or I/O integration

FiberLoops schedules fibers and nothing else. There is no `addTimer()`,
no `addReadStream()`, no socket selection. Those belong to a full event loop;
FiberLoops is the scheduling core you could build one around.

## Do not `await()` the running fiber

Awaiting the fiber that is currently executing (for example a task awaiting its own
fiber) asks PHP to resume a fiber that is already running, which raises a
`FiberError`. Await *other* tasks, not the current one.

## See also

- [Core Concepts](Core-Concepts.md) — why these limits exist.
- [Error Handling](Error-Handling.md) — the exceptions involved.
- [FAQ](FAQ.md) — common "can I…?" questions.
