# InitPHP FiberLoops

[![CI](https://github.com/InitPHP/FiberLoops/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/FiberLoops/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%E2%89%A5%208.1-777bb4.svg)](composer.json)

> A **minimal cooperative task scheduler** for PHP, built on native
> [fibers](https://www.php.net/manual/en/language.fibers.php). Run several tasks
> on a single thread, hand control back and forth at points *you* choose, and
> wait for sub-tasks to finish — in a few dozen lines of dependency-free code.

![php-fiber](https://user-images.githubusercontent.com/104234499/178588669-e6a6384b-5712-45ec-9676-fe8900fd625f.png)

## What it is (and is not)

FiberLoops is a tiny scheduler. You queue tasks with `defer()` and drive them
with `run()`. Each task is a fiber; the loop advances the tasks **round-robin**,
running each one until it **cooperatively yields** (`next()` / `sleep()`) or
returns.

Scheduling is **cooperative, not preemptive**: nothing interrupts a task. A task
keeps the CPU until it yields or finishes, so long-running tasks must call
`next()` periodically to let their siblings make progress. There is no I/O
reactor, no stream/timer polling, no threads — it is a scheduling primitive you
can build those things on top of, not a full async runtime like ReactPHP or Amp.

## Requirements

- PHP **8.1+** (fibers are a core language feature since 8.1 — no extension needed)

## Installation

```bash
composer require initphp/fiber-loops
```

## Quick start

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;

$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (['a', 'b', 'c'] as $step) {
        echo "task-1: $step\n";
        $loop->next();              // yield: let other tasks run
    }
});

$loop->defer(function () use ($loop) {
    foreach (['x', 'y', 'z'] as $step) {
        echo "task-2: $step\n";
        $loop->next();
    }
});

$loop->run();                       // drive every task to completion
```

```text
task-1: a
task-2: x
task-1: b
task-2: y
task-1: c
task-2: z
```

The two tasks interleave because each one yields with `next()` after every step.
Remove the `next()` calls and the first task would run to completion before the
second one started.

## The API

`Loop` implements `InitPHP\FiberLoops\LoopInterface`. Depend on the interface when
you want to substitute or mock the scheduler.

| Method | Description |
| ------ | ----------- |
| `defer(callable\|Fiber $task): void` | Queue a task. A callable is wrapped in a fiber. Safe to call during `run()`. |
| `run(): void` | Run every queued task to completion (blocks until the queue is empty). |
| `next(mixed $value = null): mixed` | Yield from the current task back to the scheduler. **Must run inside a fiber.** |
| `sleep(int\|float $seconds): void` | Cooperatively pause the current task. **Must run inside a fiber.** |
| `await(callable\|Fiber $task): mixed` | Run a task to completion and return its value, yielding while it works. |

### `defer()` and `run()`

`defer()` queues work; `run()` executes it. A task added *during* `run()` (from
inside another task) is picked up on the next scheduling pass, so tasks can spawn
more tasks.

### `next()`

`next()` is the yield point. Calling it suspends the current task and lets the
scheduler advance the others; the task resumes where it left off on the next pass.
It must be called from within a fiber (i.e. inside a deferred or awaited task) —
calling it from the main script throws a `LoopException`:

```php
$loop->next(); // LoopException: Loop::next() must be called from within a fiber...
```

> The bundled scheduler resumes tasks without a value, so `next()` returns `null`
> under `run()` and `await()`. The `$value` argument is reserved for custom
> drivers and is ignored here.

### `sleep()`

`sleep()` pauses the current task for at least the given number of seconds while
letting sibling tasks keep running:

```php
$loop = new Loop();

$loop->defer(function () use ($loop) {
    $loop->sleep(0.2);              // yields repeatedly for ~200ms
    foreach (range(0, 5) as $value) {
        echo $value . PHP_EOL;
    }
});

$loop->defer(function () use ($loop) {
    foreach (range(6, 9) as $value) {
        echo $value . PHP_EOL;
    }
});

$loop->run();
```

```text
6
7
8
9
0
1
2
3
4
5
```

The second task finishes first because the first one is sleeping. `sleep()` is a
**busy-wait** that yields on every iteration: siblings progress, but the loop
does not idle the CPU. `sleep(0)` (or any non-positive value) returns immediately
without yielding. See [docs/caveats.md](docs/caveats.md) for the implications.

### `await()`

`await()` runs a task to completion and returns its value. From the main script
it drives the task synchronously:

```php
$loop = new Loop();

$user = $loop->await(function () use ($loop) {
    $loop->next();                  // may yield while doing work
    return ['id' => 42, 'name' => 'Ada'];
});

echo "user: {$user['id']} / {$user['name']}\n";   // user: 42 / Ada
```

Called **from inside a task**, `await()` yields to the scheduler while the awaited
sub-task works, so other tasks keep running in the meantime:

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

`await()` accepts a callable or a `Fiber`, started or not.

## Error handling

`next()` and `sleep()` must be called from within a fiber. Doing otherwise throws
`InitPHP\FiberLoops\Exception\LoopException` (a `RuntimeException`) with an
actionable message, instead of PHP's bare `FiberError`.

## Documentation

Full guides live in [`docs/`](docs/):

| Guide | What it covers |
| ----- | -------------- |
| [Getting started](docs/getting-started.md) | Install, your first two tasks, how the loop runs them. |
| [Concepts](docs/concepts.md) | The scheduling model: fibers, the round-robin queue, cooperative yielding. |
| [API reference](docs/api/README.md) | Every method, its signature, behaviour and edge cases. |
| [await & concurrency](docs/await-and-concurrency.md) | Awaiting sub-tasks from the main context and from inside a task. |
| [Caveats & gotchas](docs/caveats.md) | Busy-wait `sleep()`, in-fiber preconditions, non-preemptive scheduling. |

## Testing

```bash
composer install
composer test        # PHPUnit
composer ci          # cs-check + phpstan + tests
```

## Contributing

Contributions are welcome. Please read the org-wide
[Contributing guide](https://github.com/InitPHP/.github/blob/main/CONTRIBUTING.md)
and the [Security policy](https://github.com/InitPHP/.github/blob/main/SECURITY.md).
Fork, branch, add tests for your change, and open a pull request.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 InitPHP — released under the [MIT License](LICENSE).
