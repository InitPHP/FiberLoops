# Troubleshooting

Symptoms, their causes, and the fix. All examples assume
`use InitPHP\FiberLoops\Loop;`.

## `LoopException: Loop::next() must be called from within a fiber…`

**Cause.** You called `next()` or `sleep()` (with a positive duration) outside a
fiber — usually from the main script rather than from inside a task.

**Fix.** Move the call into a task passed to `defer()` or `await()`:

```php
$loop = new Loop();

// ✗ Wrong — main context:
// $loop->next();

// ✓ Right — inside a task:
$loop->defer(function () use ($loop) {
    $loop->next();
    echo "ok\n";
});

$loop->run();
```

See [Error Handling](Error-Handling.md#calling-next-or-sleep-outside-a-fiber).

## `FiberError: Cannot suspend outside of a fiber`

**Cause.** The same root cause as above, but reaching PHP's native error — for
example calling `Fiber::suspend()` yourself outside a fiber. Inside FiberLoops,
`next()`/`sleep()` wrap this as a `LoopException`; if you see the raw `FiberError`,
something is suspending a fiber directly.

**Fix.** Suspend only through `next()`/`sleep()`, and only inside a task.

## My tasks don't interleave — one runs completely before the other

**Cause.** The first task never yields, so cooperative scheduling lets it run to
completion before any sibling advances.

**Fix.** Add `next()` at the points where it is fair to hand over control:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (range(1, 3) as $n) {
        echo "a$n\n";
        $loop->next();        // <-- without this, a1 a2 a3 print before any b
    }
});
$loop->defer(function () use ($loop) {
    foreach (range(1, 3) as $n) {
        echo "b$n\n";
        $loop->next();
    }
});

$loop->run();
```

See [Core Concepts → Cooperative, not preemptive](Core-Concepts.md#cooperative-not-preemptive).

## `run()` never returns — the program hangs

**Cause.** A task never terminates. `run()` only ends when the queue drains, so an
infinite task with no exit condition blocks forever.

**Fix.** Give long-lived tasks an exit condition and `return` from them. See
[Recipes → Graceful shutdown](Recipes.md#graceful-shutdown-with-a-shared-flag).

## The process pins a CPU core at 100%

**Cause.** Busy-waiting — most often, tasks spending their time in `sleep()`, which
spins rather than idling. With nothing else to do, the loop re-checks the clock as
fast as it can.

**Fix.** This is expected for a pure scheduler. If you need efficient idle waiting
on timers or I/O, FiberLoops alone is not the right tool — use a full async runtime
([ReactPHP](https://reactphp.org), [Amp](https://amphp.org)) or build a reactor on
top. See [Caveats → `sleep()` is a busy-wait](Caveats-and-Limitations.md#sleep-is-a-busy-wait).

## One task throws and the whole program stops

**Cause.** An uncaught exception inside a task propagates out of `run()`, and tasks
queued after it on that pass do not run.

**Fix.** Catch inside the task to isolate the failure:

```php
$loop = new Loop();

$loop->defer(function () {
    try {
        riskyWork();
    } catch (\Throwable $e) {
        // log and move on — the loop keeps running
    }
});

$loop->run();
```

See [Error Handling → Exceptions thrown inside a task](Error-Handling.md#exceptions-thrown-inside-a-task).

## `FiberError: Cannot resume a fiber that is not suspended` / `…already running`

**Cause.** Resuming or awaiting a fiber that is currently running — for example
awaiting the task's own fiber, or sharing one `Fiber` object between two awaiters.

**Fix.** `await()` *other* tasks, not the currently executing one, and do not hand
the same `Fiber` instance to two places. See
[Caveats → Do not `await()` the running fiber](Caveats-and-Limitations.md#do-not-await-the-running-fiber).

## Composer says the package needs a newer PHP

**Cause.** FiberLoops requires **PHP 8.2+** (fibers do not exist before 8.1).

**Fix.** Upgrade PHP to 8.2 or later, then `composer require initphp/fiber-loops`.
Check your version with `php -v`.

## Still stuck?

- [Open an issue](https://github.com/InitPHP/FiberLoops/issues) with a minimal
  reproducer (a short standalone script).
- Ask in the org-wide [Discussions](https://github.com/orgs/InitPHP/discussions).
