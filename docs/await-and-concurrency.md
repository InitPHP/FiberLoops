# await & concurrency

`await()` runs a single task to completion and gives you its return value. How it
behaves depends on **where you call it from**.

## From the main context

Called from the main script (not inside a fiber), `await()` drives the task to
completion synchronously and returns its value. The task may yield with `next()`
as many times as it likes — `await()` resumes it until it returns.

```php
require 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;

$loop = new Loop();

$user = $loop->await(function () use ($loop) {
    $loop->next();          // may yield while doing work
    $loop->next();
    return ['id' => 42, 'name' => 'Ada'];
});

echo "user: {$user['id']} / {$user['name']}\n";
```

```text
user: 42 / Ada
```

This works regardless of how many times the task yields. (Earlier versions threw
`FiberError: Cannot suspend outside of a fiber` once the task yielded twice — that
is fixed.)

## From inside a task

Called from inside a running task, `await()` cooperatively **yields to the
scheduler between the awaited task's steps**, so the loop's other tasks keep
running while you wait for the result.

```php
require 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;

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

The `heartbeat` task runs throughout — one tick for each step the awaited
sub-task takes — instead of being frozen until the worker's `await()` returns.

## Awaiting a `Fiber` directly

`await()` accepts a `Fiber`, started or not. This is handy when another part of
your code already created the fiber:

```php
$fiber = new Fiber(function () {
    Fiber::suspend();
    Fiber::suspend();
    return 99;
});
$fiber->start();            // already started elsewhere

$loop = new Loop();
echo $loop->await($fiber);  // 99
```

(An unstarted fiber is started for you; an already-terminated fiber simply yields
its return value.)

## Return values

`await()` returns whatever the task returns, or `null` if it returns nothing:

```php
$nothing = $loop->await(function () {
    // no return statement
});
// $nothing === null
```

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
// $result === 21
```

## Things to know

- **Do not `await()` the fiber that is currently running** (for example the task's
  own fiber). Resuming the running fiber is invalid and PHP will raise a
  `FiberError`.
- `await()` runs the task to completion before returning. It is *wait-for-result*,
  not *fire-and-forget* — for fire-and-forget, use [`defer()`](api/README.md#defer).
