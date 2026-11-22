# Awaiting Results

`await()` runs a single task to completion and gives you its **return value**. It
is the bridge between "fire a task and forget it" (`defer()`) and "I need this
result before I continue." All examples assume:

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;
```

```php
public function await(callable|Fiber $task): mixed
```

How `await()` behaves depends on **where you call it from**.

## From the main context

Called from the main script (not inside a fiber), `await()` drives the task to
completion synchronously and returns its value. The task may yield with `next()`
as often as it likes:

```php
$loop = new Loop();

$user = $loop->await(function () use ($loop) {
    $loop->next();        // pretend this is I/O
    $loop->next();
    return ['id' => 42, 'name' => 'Ada'];
});

echo "user: {$user['id']} / {$user['name']}\n";
```

```text
user: 42 / Ada
```

This works no matter how many times the task yields — there is no special case for
"awaiting from outside the loop." (Releases before 2.0.0 threw
`FiberError: Cannot suspend outside of a fiber` when the task yielded more than
once; that was fixed in 2.0.0.)

## From inside a task

This is where `await()` earns its keep. Called from inside a running task, it
**yields to the scheduler between the awaited task's steps**, so the loop's other
tasks keep running while you wait for the result.

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    echo "worker: awaiting a sub-task\n";
    $sum = $loop->await(function () use ($loop) {
        $total = 0;
        foreach (range(1, 3) as $n) {
            $total += $n;
            $loop->next();
        }
        return $total;
    });
    echo "worker: sub-task returned $sum\n";
});

$loop->defer(function () use ($loop) {
    foreach (['tick', 'tick', 'tick'] as $t) {
        echo "heartbeat: $t\n";
        $loop->next();
    }
});

$loop->run();
```

```text
worker: awaiting a sub-task
heartbeat: tick
heartbeat: tick
heartbeat: tick
worker: sub-task returned 6
```

The `heartbeat` task runs throughout — one tick per step of the awaited sub-task —
instead of being frozen until `await()` returns.

## Return values

`await()` returns whatever the task returns, or `null` if it returns nothing:

```php
$loop = new Loop();

$value   = $loop->await(fn () => 'a result');
$nothing = $loop->await(function () { /* no return */ });

var_dump($value, $nothing);
```

```text
string(8) "a result"
NULL
```

## Awaiting a `Fiber` directly

`await()` accepts a `Fiber`, whether or not it has already been started. This is
handy when another part of your code created the fiber:

```php
$fiber = new Fiber(function () {
    Fiber::suspend();
    Fiber::suspend();
    return 99;
});
$fiber->start();          // already started elsewhere

$loop = new Loop();
echo $loop->await($fiber) . "\n";
```

```text
99
```

An unstarted fiber is started for you; an already-terminated fiber simply yields
its return value.

## Nesting

`await()` calls nest naturally — a task you await can itself await another:

```php
$loop = new Loop();

$result = $loop->await(function () use ($loop) {
    $inner = $loop->await(function () use ($loop) {
        $loop->next();
        return 20;
    });
    return $inner + 1;
});

echo $result . "\n";
```

```text
21
```

## `await()` waits for one result — it is not "run these in parallel"

`await()` runs a single task to completion before returning. If you want **several**
tasks running concurrently and then collect their results, do not chain `await()`
calls — `defer()` the tasks instead and let them write their results into a shared
place. See
[Recipes → Collecting results from concurrent tasks](Recipes.md#collecting-results-from-concurrent-tasks).

## Things to know

- **Do not `await()` the currently running fiber** (for example a task's own
  fiber). Resuming the running fiber is invalid and PHP raises a `FiberError`.
- `await()` is *wait-for-result*, not *fire-and-forget*. For fire-and-forget, use
  [`defer()`](Deferring-and-Running-Tasks.md).
- An exception thrown inside the awaited task propagates out of `await()` — see
  [Error Handling](Error-Handling.md#exceptions-thrown-inside-a-task).

## See also

- [Deferring & Running Tasks](Deferring-and-Running-Tasks.md) — fire-and-forget tasks.
- [Recipes](Recipes.md) — concurrent result collection, pipelines and more.
- [API Reference](API-Reference.md#await) — the exact contract.
