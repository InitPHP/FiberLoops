# API Reference

The complete public surface of FiberLoops. Three types, one of them an interface
and one an exception.

```php
namespace InitPHP\FiberLoops;

final class Loop implements LoopInterface
```

`Loop` is the scheduler. Construct it with **no arguments** — tasks are added only
through [`defer()`](#defer) and [`await()`](#await):

```php
use InitPHP\FiberLoops\Loop;

$loop = new Loop();
```

- [`defer()`](#defer)
- [`run()`](#run)
- [`next()`](#next)
- [`sleep()`](#sleep)
- [`await()`](#await)
- [Exceptions](#exceptions)
- [LoopInterface](#loopinterface)

---

## `defer()`

```php
public function defer(callable|Fiber $task): void
```

Queue a task to be run by [`run()`](#run).

| | |
| --- | --- |
| **Parameters** | `$task` — a `callable` (wrapped in a new `Fiber`) or a `Fiber` (queued as-is). A callable is invoked with **no arguments**. |
| **Returns** | `void` |
| **Notes** | Safe to call during `run()`; a task deferred mid-run is picked up on the next pass. |

```php
$loop->defer(function () {
    echo "queued work\n";
});
```

See [Deferring & Running Tasks](Deferring-and-Running-Tasks.md).

---

## `run()`

```php
public function run(): void
```

Run every queued task to completion. Blocks until the queue is empty, advancing
each task one step per pass (start → resume → remove when terminated).

| | |
| --- | --- |
| **Parameters** | none |
| **Returns** | `void` |
| **Notes** | An empty loop is a no-op. A task that never returns blocks forever. An uncaught exception inside a task propagates out of `run()`. |

```php
$loop->run();
```

See [Core Concepts → How `run()` advances tasks](Core-Concepts.md#how-run-advances-tasks).

---

## `next()`

```php
public function next(mixed $value = null): mixed
```

Cooperatively yield from the current task back to the scheduler. The task resumes
on the next pass, just after this call.

| | |
| --- | --- |
| **Parameters** | `$value` — value yielded to the driver. **Ignored by the bundled scheduler.** |
| **Returns** | `mixed` — the value the driver resumes with; **`null`** under `run()` / `await()`. |
| **Throws** | [`LoopException`](#exceptions) if called outside a fiber. |

```php
$loop->defer(function () use ($loop) {
    echo "before\n";
    $loop->next();
    echo "after\n";
});
```

See [Yielding & Sleeping → `next()`](Yielding-and-Sleeping.md#next).

---

## `sleep()`

```php
public function sleep(int|float $seconds): void
```

Cooperatively pause the current task for **at least** `$seconds`, while sibling
tasks keep running.

| | |
| --- | --- |
| **Parameters** | `$seconds` — duration. Non-positive returns immediately without yielding. |
| **Returns** | `void` |
| **Throws** | [`LoopException`](#exceptions) if called outside a fiber (except `sleep(0)`, which never yields). |
| **Notes** | A **busy-wait** that yields via `next()` on each iteration — the CPU stays busy; the loop never idles. |

```php
$loop->defer(function () use ($loop) {
    $loop->sleep(0.2);
    echo "woke up\n";
});
```

See [Yielding & Sleeping → `sleep()`](Yielding-and-Sleeping.md#sleep).

---

## `await()`

```php
public function await(callable|Fiber $task): mixed
```

Run a task to completion and return its value.

| | |
| --- | --- |
| **Parameters** | `$task` — a `callable` (wrapped in a new `Fiber`) or a `Fiber`, started or not. |
| **Returns** | `mixed` — the task's return value, or `null` if it returns none. |
| **Behaviour** | From the **main context**, drives the task synchronously. From **inside a task**, yields between the task's steps so siblings keep running. |

```php
$result = $loop->await(function () use ($loop) {
    $loop->next();
    return 42;
});
// $result === 42
```

See [Awaiting Results](Awaiting-Results.md).

---

## Exceptions

```php
namespace InitPHP\FiberLoops\Exception;

class LoopException extends \RuntimeException
```

Thrown when the loop is driven in a way its cooperative model does not allow —
most commonly, calling [`next()`](#next) or [`sleep()`](#sleep) from outside a
fiber. It wraps what would otherwise be a bare `FiberError` in a package-namespaced
type with an actionable message. Catch it as `LoopException`, `RuntimeException`,
or `Throwable`.

```php
use InitPHP\FiberLoops\Exception\LoopException;

try {
    $loop->next();          // from the main script
} catch (LoopException $e) {
    echo $e->getMessage();
    // Loop::next() must be called from within a fiber, e.g. inside a task
    // passed to Loop::defer() or Loop::await().
}
```

See [Error Handling](Error-Handling.md).

---

## `LoopInterface`

```php
namespace InitPHP\FiberLoops;

interface LoopInterface
{
    public function defer(callable|Fiber $task): void;
    public function run(): void;
    public function next(mixed $value = null): mixed;
    public function sleep(int|float $seconds): void;
    public function await(callable|Fiber $task): mixed;
}
```

`Loop` implements this contract. Depend on `LoopInterface` (not the concrete
`Loop`) when you want to swap scheduling strategies or substitute a test double —
see [Testing → Depend on `LoopInterface`](Testing.md#depend-on-loopinterface-for-substitution).
