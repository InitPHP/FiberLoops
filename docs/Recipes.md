# Recipes

Practical patterns built from the five methods. Every recipe is a complete,
runnable script and the output shown is real. Each assumes:

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;
```

- [Collecting results from concurrent tasks](#collecting-results-from-concurrent-tasks)
- [Producer and consumer over a shared buffer](#producer-and-consumer-over-a-shared-buffer)
- [Graceful shutdown with a shared flag](#graceful-shutdown-with-a-shared-flag)
- [A heartbeat alongside a long job](#a-heartbeat-alongside-a-long-job)
- [Aggregating awaited results](#aggregating-awaited-results)

## Collecting results from concurrent tasks

`await()` runs one task at a time. To run **several** tasks concurrently and
collect their results, `defer()` each one and have it write into a shared array;
the values are all present once `run()` returns.

```php
$loop = new Loop();
$results = [];

foreach ([10, 20, 30] as $i => $input) {
    $loop->defer(function () use ($loop, $input, $i, &$results) {
        $acc = 0;
        foreach (range(1, 3) as $step) {   // some staged work that yields
            $acc += $input;
            $loop->next();
        }
        $results[$i] = $acc;
    });
}

$loop->run();

ksort($results);
echo implode(', ', $results) . "\n";
```

```text
30, 60, 90
```

The three tasks interleave step by step (concurrency), and each stores its result
under its own key. Sort by key if you need the original order back.

## Producer and consumer over a shared buffer

A classic pipeline: one task produces items into a buffer, another consumes them.
They coordinate with `next()` and a shared `$done` flag — no locks needed, because
only one task runs at a time.

```php
$loop = new Loop();
$buffer = [];
$done = false;

// Producer
$loop->defer(function () use ($loop, &$buffer, &$done) {
    foreach (range(1, 4) as $n) {
        $buffer[] = $n;
        echo "produced $n\n";
        $loop->next();
    }
    $done = true;
});

// Consumer
$loop->defer(function () use ($loop, &$buffer, &$done) {
    while (!$done || $buffer !== []) {
        if ($buffer === []) {        // nothing yet — yield and re-check
            $loop->next();
            continue;
        }
        $item = array_shift($buffer);
        echo "consumed $item\n";
        $loop->next();
    }
});

$loop->run();
```

```text
produced 1
consumed 1
produced 2
consumed 2
produced 3
consumed 3
produced 4
consumed 4
```

The consumer's `while (!$done || $buffer !== [])` condition keeps it alive until
the producer is finished *and* the buffer is drained.

## Graceful shutdown with a shared flag

`run()` only ends when every task returns, and there is no built-in stop signal.
For a long-lived task, give it an exit condition — here, a shared `$stop` flag a
supervisor flips:

```php
$loop = new Loop();
$stop = false;

// Long-lived worker
$loop->defer(function () use ($loop, &$stop) {
    $i = 0;
    while (!$stop) {
        echo "working $i\n";
        $i++;
        $loop->next();
    }
    echo "worker stopped after $i iterations\n";
});

// Supervisor: let it run a few turns, then signal stop
$loop->defer(function () use ($loop, &$stop) {
    foreach (range(1, 3) as $_) {
        $loop->next();
    }
    $stop = true;
    echo "supervisor: signalled stop\n";
});

$loop->run();
```

```text
working 0
working 1
working 2
working 3
supervisor: signalled stop
worker stopped after 4 iterations
```

In a real program the supervisor might flip `$stop` from a signal handler
(`pcntl_signal`) or after a deadline. The key idea is that the worker checks the
flag at its yield point.

## A heartbeat alongside a long job

Because `await()` yields while it waits, a long sub-task does not freeze the rest
of the loop. Here a worker awaits a multi-step job while a heartbeat keeps ticking:

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

## Aggregating awaited results

When you genuinely need results **in sequence** — each depends on the last, or you
just want them in order — chain `await()` calls. From the main context this reads
like ordinary synchronous code:

```php
$loop = new Loop();

$fetch = function (int $id) use ($loop): array {
    $loop->next();                 // pretend this is I/O
    return ['id' => $id, 'score' => $id * 10];
};

$total = 0;
foreach ([1, 2, 3] as $id) {
    $row = $loop->await(fn () => $fetch($id));
    echo "fetched #{$row['id']} -> {$row['score']}\n";
    $total += $row['score'];
}

echo "total: $total\n";
```

```text
fetched #1 -> 10
fetched #2 -> 20
fetched #3 -> 30
total: 60
```

Use this when order matters; use
[concurrent collection](#collecting-results-from-concurrent-tasks) when it does
not and you want the tasks to overlap.

## See also

- [Awaiting Results](Awaiting-Results.md) — the semantics behind these patterns.
- [Caveats & Limitations](Caveats-and-Limitations.md) — the edges to keep in mind.
