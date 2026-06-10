# Caveats & gotchas

FiberLoops is a small, sharp scheduling primitive. Knowing its edges keeps you out
of trouble.

## Scheduling is cooperative, not preemptive

Nothing interrupts a running task. Control returns to the loop only when a task
**yields** (`next()` / `sleep()`) or **returns**. A task that never yields starves
every other task until it finishes:

```php
$loop->defer(function () {
    while (true) {          // never yields
        // ... the loop and every other task are now frozen
    }
});
```

Place a `next()` inside any long-running or unbounded loop so siblings get a turn.

## `sleep()` is a busy-wait

`sleep()` does **not** put the process to sleep. It spins, calling `next()` on
each iteration until the deadline passes:

```php
// conceptually:
$until = microtime(true) + $seconds;
while (microtime(true) < $until) {
    $this->next();          // yield to siblings, then re-check the clock
}
```

Consequences:

- **Siblings keep running** while one task sleeps — that is the point.
- **The CPU stays busy.** If *every* task is sleeping, the loop spins at 100% CPU
  re-checking the clock. FiberLoops has no idle/poll phase; it is a scheduler, not
  an I/O reactor. For genuinely idle waiting (timers, sockets), reach for a full
  async runtime such as ReactPHP or Amp, or build a real reactor on top of these
  primitives.
- `sleep()` guarantees *at least* the requested duration, not an exact one — the
  actual pause depends on how often the loop comes back around.

## `sleep(0)` is a pure no-op

Because the busy-wait condition is false immediately, `sleep(0)` (or any
non-positive value) returns **without yielding even once**. If you want "yield one
turn", call `next()` directly — do not rely on `sleep(0)` to do it.

## `next()` and `sleep()` must run inside a fiber

Both call `Fiber::suspend()` under the hood, which is only legal inside a fiber.
Calling them from the main script throws
[`LoopException`](api/README.md#exceptions):

```php
$loop = new Loop();
$loop->next();   // LoopException: Loop::next() must be called from within a fiber...
```

In practice this means: only call `next()` / `sleep()` from inside a task you
passed to `defer()` or `await()`. (`sleep(0)` is the one exception — it never
reaches the suspend, so it is safe anywhere.)

## One task at a time — concurrency, not parallelism

Fibers interleave on a single thread; they do not run in parallel. FiberLoops is
ideal for overlapping tasks that yield (cooperative pipelines, generators,
step-wise state machines), not for speeding up CPU-bound work across cores.

## `run()` returns only when the queue drains

`run()` blocks until every task has terminated. If you need it to stop early, give
your tasks their own exit condition (a shared flag, a maximum iteration count) and
have them `return` — there is no built-in stop signal.

## Exceptions thrown inside a task propagate out of `run()`

A task is a fiber; an uncaught exception inside it surfaces where the fiber is
started or resumed — that is, out of `run()` (or `await()`). Wrap risky work in
your task with `try/catch` if you want the loop to survive a failing task.
