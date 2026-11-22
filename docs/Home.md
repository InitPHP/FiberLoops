# InitPHP FiberLoops — Wiki

Welcome to the official documentation for **`initphp/fiber-loops`** — a
**minimal cooperative task scheduler** for PHP, built on native
[fibers](https://www.php.net/manual/en/language.fibers.php).

It lets you run several tasks on a single thread, hand control back and forth at
points **you** choose, and wait for a sub-task's result — in a few dozen lines of
dependency-free code. There is no I/O reactor, no thread pool, no magic: it is a
small, sharp scheduling primitive you can read in one sitting and build on.

```bash
composer require initphp/fiber-loops
```

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;

$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (['a', 'b', 'c'] as $step) {
        echo "task-1: $step\n";
        $loop->next();              // yield: let the other task run
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

The two tasks interleave because each yields with `next()` after every step.
That single idea — *cooperative yielding* — is the whole library.

## What it is (and is not)

| It **is** | It is **not** |
| --------- | ------------- |
| A cooperative scheduler: tasks take turns when they yield | A preemptive one: nothing interrupts a running task |
| Concurrency on one thread (interleaved progress) | Parallelism (work running on multiple cores) |
| A primitive you build reactors, pipelines and state machines on | A full async runtime like [ReactPHP](https://reactphp.org) or [Amp](https://amphp.org) |
| Dependency-free, under 100 lines of code, PHP 8.2+ | An I/O event loop with stream/timer polling |

## Start here

- **New to the package?** Read [Installation](Installation.md), then [Quick Start](Quick-Start.md).
- **Want the big picture?** Read [Core Concepts](Core-Concepts.md) — how the scheduler actually works.
- **Looking for patterns?** Jump to [Recipes](Recipes.md).
- **Need the exact contract?** See the [API Reference](API-Reference.md).

Use the sidebar to navigate. **Every code example on this wiki is a complete,
runnable script, and the output shown beneath it is the program's real output.**

## Requirements

- PHP **8.2+** — fibers are a core language feature since 8.1, so there is nothing
  else to install.

## Links

- [GitHub repository](https://github.com/InitPHP/FiberLoops)
- [Packagist package](https://packagist.org/packages/initphp/fiber-loops)
- [Changelog](https://github.com/InitPHP/FiberLoops/blob/main/CHANGELOG.md)
