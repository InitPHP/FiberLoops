# Yielding & Sleeping

`next()` and `sleep()` are how a task **steps aside** so the rest of the loop can
make progress. They are the cooperative half of cooperative scheduling. All
examples assume:

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;
```

## `next()`

```php
public function next(mixed $value = null): mixed
```

`next()` suspends the current task and returns control to the scheduler. On the
loop's next pass the task resumes on the line *after* the call.

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    echo "before\n";
    $loop->next();        // hand control back to the loop here
    echo "after\n";
});

$loop->run();
```

```text
before
after
```

With a single task there is nothing else to run, so it simply resumes. The point
of `next()` is what happens with **two or more** tasks — they interleave at each
yield (see [Quick Start](Quick-Start.md#1-run-two-tasks-that-take-turns)).

### Place `next()` inside long loops

The rule of thumb: any loop that could run for a while should `next()` on each
iteration, so it does not starve its siblings.

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (range(1, 3) as $n) {
        echo "worker: chunk $n\n";
        $loop->next();        // let others run between chunks
    }
});

$loop->defer(function () use ($loop) {
    echo "monitor: tick\n";
    $loop->next();
    echo "monitor: tick\n";
});

$loop->run();
```

```text
worker: chunk 1
monitor: tick
worker: chunk 2
monitor: tick
worker: chunk 3
```

### The `$value` argument and return value

`next()` accepts an optional `$value` and returns `mixed`. These exist for fibers
driven by a *custom* driver that resumes them with a value. **The bundled
scheduler resumes tasks without a value**, so under `run()` (and `await()`)
`next()` always returns `null`, and the `$value` you pass is ignored:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    $returned = $loop->next('this is ignored by the scheduler');
    var_dump($returned);
});

$loop->run();
```

```text
NULL
```

Treat `next()` as a plain "yield now" in everyday use.

### `next()` must run inside a fiber

`next()` calls `Fiber::suspend()`, which is only legal inside a fiber. Calling it
from the main script throws a
[`LoopException`](Error-Handling.md#calling-next-or-sleep-outside-a-fiber):

```php
use InitPHP\FiberLoops\Exception\LoopException;

$loop = new Loop();

try {
    $loop->next();        // called from the main script, not inside a task
} catch (LoopException $e) {
    echo $e->getMessage() . "\n";
}
```

```text
Loop::next() must be called from within a fiber, e.g. inside a task passed to Loop::defer() or Loop::await().
```

In practice this just means: only call `next()` from inside a task you passed to
`defer()` or `await()`.

## `sleep()`

```php
public function sleep(int|float $seconds): void
```

`sleep()` cooperatively pauses the current task for **at least** `$seconds`, while
letting sibling tasks keep running:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    echo "sleeper: going to sleep\n";
    $loop->sleep(0.2);
    echo "sleeper: awake\n";
});

$loop->defer(function () use ($loop) {
    foreach (range(1, 3) as $n) {
        echo "ticker: $n\n";
        $loop->next();
    }
});

$loop->run();
```

```text
sleeper: going to sleep
ticker: 1
ticker: 2
ticker: 3
sleeper: awake
```

The ticker runs to completion while the sleeper waits, then the sleeper wakes.

### `sleep()` is a busy-wait

This is the most important thing to understand about `sleep()`. It does **not**
put the process to sleep. Conceptually it is:

```php
$until = microtime(true) + $seconds;
while (microtime(true) < $until) {
    $this->next();      // yield to siblings, then re-check the clock
}
```

So:

- **Siblings keep running** while one task sleeps — that is the whole point.
- **The CPU stays busy.** If every task is sleeping, the loop spins at 100% CPU
  re-checking the clock. FiberLoops has no idle phase; it is a scheduler, not an
  I/O reactor. For genuinely idle waiting, see
  [Caveats & Limitations](Caveats-and-Limitations.md#sleep-is-a-busy-wait).
- It guarantees *at least* the requested time, not an exact duration — the real
  pause depends on how often the loop comes back around.

### `sleep(0)` is a pure no-op

Because the loop condition is immediately false, `sleep(0)` — or any non-positive
value — returns **without yielding even once**. It does not even require a fiber.
If you want "yield exactly one turn," call `next()` directly, not `sleep(0)`.

### `sleep()` must run inside a fiber

`sleep()` yields via `next()`, so the same precondition applies: calling it from
the main script (with a positive duration) throws a
[`LoopException`](Error-Handling.md#calling-next-or-sleep-outside-a-fiber).

## See also

- [Awaiting Results](Awaiting-Results.md) — running a task and getting its value.
- [Error Handling](Error-Handling.md) — what `LoopException` means and how to avoid it.
- [Caveats & Limitations](Caveats-and-Limitations.md) — the busy-wait, preemption, and more.
