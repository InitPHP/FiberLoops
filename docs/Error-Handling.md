# Error Handling

FiberLoops fails loudly and predictably. There are two things to understand: the
one exception the package itself throws, and how exceptions from *your* tasks
behave. All examples assume:

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;
use InitPHP\FiberLoops\Exception\LoopException;
```

## `LoopException`

```php
namespace InitPHP\FiberLoops\Exception;

class LoopException extends \RuntimeException
```

`LoopException` is thrown when the loop is driven in a way its cooperative model
does not allow. In practice that means **one** thing: calling `next()` or
`sleep()` from outside a fiber. It extends `\RuntimeException`, so you can catch it
as `LoopException`, `RuntimeException`, or `Throwable`.

## Calling `next()` or `sleep()` outside a fiber

`next()` and `sleep()` suspend the current fiber, which is only legal **inside** a
fiber. Call them from the main script and you get a `LoopException` with a message
that tells you exactly what to do:

```php
$loop = new Loop();

try {
    $loop->next();        // not inside a task
} catch (LoopException $e) {
    echo $e->getMessage() . "\n";
}
```

```text
Loop::next() must be called from within a fiber, e.g. inside a task passed to Loop::defer() or Loop::await().
```

The same applies to `sleep()` with a positive duration (it yields via `next()`):

```php
$loop = new Loop();

try {
    $loop->sleep(0.1);    // not inside a task
} catch (LoopException $e) {
    echo "caught: " . get_class($e) . "\n";
}
```

```text
caught: InitPHP\FiberLoops\Exception\LoopException
```

> Without this guard, PHP would surface a bare `FiberError: Cannot suspend outside
> of a fiber`. `LoopException` wraps that precondition in a package-namespaced type
> with an actionable message. (Note: `sleep(0)` is a no-op that never yields, so it
> does **not** throw even outside a fiber.)

### The fix

Only call `next()` / `sleep()` from inside a task:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    $loop->sleep(0.1);    // fine — we are inside a fiber here
    echo "done\n";
});

$loop->run();
```

```text
done
```

## Exceptions thrown inside a task

A task is a fiber. An **uncaught** exception thrown inside a task surfaces where the
fiber is being driven — that is, **out of `run()`** (or out of `await()` if you are
awaiting the task). The loop does not swallow it:

```php
$loop = new Loop();

$loop->defer(function () {
    throw new \RuntimeException('boom');
});

try {
    $loop->run();
} catch (\RuntimeException $e) {
    echo "propagated out of run(): " . $e->getMessage() . "\n";
}
```

```text
propagated out of run(): boom
```

### Keep the loop alive: catch inside the task

If you want the loop to survive a failing task, wrap the risky work in a
`try/catch` **inside the task**. The other tasks then run unaffected:

```php
$loop = new Loop();

$loop->defer(function () {
    try {
        throw new \RuntimeException('boom');
    } catch (\RuntimeException $e) {
        echo "task handled: {$e->getMessage()}\n";
    }
});

$loop->defer(function () {
    echo "other task ran fine\n";
});

$loop->run();
```

```text
task handled: boom
other task ran fine
```

> **One failing task can stop the loop.** Because an uncaught exception breaks out
> of `run()`, tasks queued after the failing one on that pass may not run. If a
> task does work that can fail and you want isolation, catch inside the task.

## Quick reference

| Situation | What happens |
| --------- | ------------ |
| `next()` / `sleep(> 0)` outside a fiber | `LoopException` thrown |
| `sleep(0)` outside a fiber | No-op, no exception |
| Uncaught exception inside a task | Propagates out of `run()` / `await()` |
| Exception caught inside the task | Loop continues normally |

## See also

- [Yielding & Sleeping](Yielding-and-Sleeping.md) — the methods that require a fiber.
- [Troubleshooting](Troubleshooting.md) — symptoms and fixes.
- [API Reference](API-Reference.md#exceptions) — the exception class.
