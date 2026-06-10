# API reference

```php
namespace InitPHP\FiberLoops;

final class Loop implements LoopInterface
```

`Loop` is the scheduler. It implements `LoopInterface`, so you can type-hint and
substitute against the interface. Construct it with no arguments:

```php
use InitPHP\FiberLoops\Loop;

$loop = new Loop();
```

Tasks are added only through [`defer()`](#defer) and [`await()`](#await); there is
no constructor argument.

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

- A **callable** is wrapped in a new `Fiber`. It is invoked with **no arguments**.
- A **`Fiber`** is queued as-is.

`defer()` may be called at any time, including from inside a running task. A task
deferred during `run()` is picked up on the next scheduling pass.

```php
$loop->defer(function () {
    echo "hello from a task\n";
});

$loop->defer(new Fiber(function () {
    echo "hello from a fiber\n";
}));
```

---

## `run()`

```php
public function run(): void
```

Run every queued task to completion. Blocks until the queue is empty, advancing
each task one step per pass (start → resume → remove when terminated). See
[Concepts → How `run()` advances tasks](../concepts.md#how-run-advances-tasks).

Running an empty loop is a no-op. A task that never yields and never returns will
block the loop forever — that is your responsibility, not the loop's. An uncaught
exception thrown inside a task propagates out of `run()`.

```php
$loop->run();
```

---

## `next()`

```php
public function next(mixed $value = null): mixed
```

Cooperatively yield from the current task back to the scheduler. The task is
suspended and resumed, just after this call, on the loop's next pass.

**Precondition:** must be called from within a fiber (inside a deferred or awaited
task). Calling it from the main script throws [`LoopException`](#exceptions).

**Return value:** the bundled scheduler resumes tasks without a value, so under
[`run()`](#run) and [`await()`](#await) `next()` returns `null`. The `$value`
argument is the value yielded to the driver; the bundled scheduler ignores it. It
exists so a custom driver can pass values in and out of a fiber.

```php
$loop->defer(function () use ($loop) {
    echo "before yield\n";
    $loop->next();          // control returns to the scheduler here
    echo "after yield\n";
});
```

---

## `sleep()`

```php
public function sleep(int|float $seconds): void
```

Cooperatively pause the current task for **at least** `$seconds`, letting sibling
tasks keep running in the meantime.

**Precondition:** must be called from within a fiber — it yields via `next()`.

**Behaviour:**

- Implemented as a busy-wait that calls `next()` on every iteration. Siblings make
  progress, but the loop does not idle the CPU. See
  [Caveats](../caveats.md#sleep-is-a-busy-wait).
- `sleep(0)` — or any non-positive duration — returns immediately **without**
  yielding. (Because it never reaches `next()`, `sleep(0)` does not even require a
  fiber.)

```php
$loop->defer(function () use ($loop) {
    $loop->sleep(0.2);      // ~200ms, while other tasks run
    echo "woke up\n";
});
```

---

## `await()`

```php
public function await(callable|Fiber $task): mixed
```

Run a task to completion and return its value.

- Accepts a **callable** (wrapped in a new `Fiber`) or a **`Fiber`**, whether it
  has been started or not.
- From the **main context**, drives the task to completion synchronously.
- From **inside a task**, yields to the scheduler between the awaited task's steps,
  so sibling tasks keep running while you wait.

**Return value:** the awaited task's return value, or `null` if it returns none.

```php
// From the main context:
$result = $loop->await(function () use ($loop) {
    $loop->next();
    return 42;
});
// $result === 42
```

See [await & concurrency](../await-and-concurrency.md) for the in-task behaviour
and worked examples.

---

## Exceptions

```php
namespace InitPHP\FiberLoops\Exception;

class LoopException extends \RuntimeException
```

Thrown when the loop is driven in a way its cooperative model does not allow —
most commonly, calling [`next()`](#next) or [`sleep()`](#sleep) from outside a
fiber. It wraps what would otherwise be a bare `FiberError` in a package-namespaced
type with an actionable message:

```php
try {
    $loop->next();          // from the main script
} catch (\InitPHP\FiberLoops\Exception\LoopException $e) {
    echo $e->getMessage();
    // Loop::next() must be called from within a fiber, e.g. inside a task
    // passed to Loop::defer() or Loop::await().
}
```

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

Depend on `LoopInterface` rather than the concrete `Loop` when you want to swap
scheduling strategies or substitute a test double.
