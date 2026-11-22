# FAQ

### Is this a replacement for ReactPHP or Amp?

No. ReactPHP and Amp are full async runtimes: they have I/O event loops that poll
sockets, streams, timers and signals, and idle efficiently when there is nothing to
do. FiberLoops is just the **scheduling core** — it interleaves fibers and nothing
else. Use it when you want cooperative concurrency over tasks you control, or as a
primitive to build something larger on. For real non-blocking I/O, use a full
runtime.

### Does it do asynchronous I/O?

No. There is no stream/socket/timer integration. A task that does a blocking
`file_get_contents()` or `PDO::query()` blocks the whole loop for that call.
FiberLoops overlaps tasks that **yield**; it cannot make a blocking call
non-blocking.

### What is the difference between concurrency and parallelism here?

FiberLoops gives **concurrency**: multiple tasks make progress by taking turns on a
single thread. It does **not** give **parallelism**: nothing runs on two CPU cores
at once. Only one fiber executes at any instant. See
[Caveats → Concurrency, not parallelism](Caveats-and-Limitations.md#concurrency-not-parallelism).

### Why did my `next()` / `sleep()` throw a `LoopException`?

You called it outside a fiber — most likely from the main script instead of from
inside a task. `next()` and `sleep()` suspend the current fiber, which is only
legal inside one. Move the call into a task you pass to `defer()` or `await()`. See
[Error Handling](Error-Handling.md#calling-next-or-sleep-outside-a-fiber).

### Can I add tasks while the loop is running?

Yes. `defer()` is safe to call during `run()`; the new task is picked up on the
next pass. This is how you build dispatchers that spawn workers. See
[Deferring & Running Tasks → Spawning tasks](Deferring-and-Running-Tasks.md#spawning-tasks-while-the-loop-runs).

### How do I stop the loop early?

There is no built-in stop call — `run()` ends when the queue drains. Give your
long-lived tasks an exit condition (a shared flag, a counter, a deadline) and have
them `return`. See
[Recipes → Graceful shutdown](Recipes.md#graceful-shutdown-with-a-shared-flag).

### Does `sleep()` use the CPU?

Yes. `sleep()` is a **busy-wait**: it spins, yielding to siblings and re-checking
the clock until the deadline. It does not idle the process. If every task is
sleeping, the loop runs at 100% CPU. See
[Caveats → `sleep()` is a busy-wait](Caveats-and-Limitations.md#sleep-is-a-busy-wait).

### Can I `await()` several tasks so they run in parallel?

Not by chaining `await()` — each call runs one task to completion before the next
starts. To overlap tasks and then collect their results, `defer()` them and have
each write into a shared array. See
[Recipes → Collecting results from concurrent tasks](Recipes.md#collecting-results-from-concurrent-tasks).

### Do I need a PHP extension?

No. Fibers are part of core PHP since 8.1. The only requirement is **PHP 8.2+**.

### Why fibers instead of generators?

Generators (`yield`) are *shallow*: only the generator function itself can yield,
so the suspension point has to be threaded back through every caller. Fibers are
*stackful*: a task can call into helper functions and any of them can yield, with
no special plumbing. That makes `sleep()` and `await()` — which yield from inside
nested calls — natural to write.

### Is the execution order deterministic?

Yes. Cooperative scheduling means the interleaving is fixed and reproducible: tasks
switch only at the `next()`/`sleep()`/`await()` points you wrote, and the loop
advances them round-robin. This makes loop-based code easy to test — see
[Testing](Testing.md).

### Can I run nested loops, or call `run()` from inside a task?

The intended model is a single `run()` driving a flat set of tasks (which may
spawn more tasks). Calling `run()` recursively from inside a task is not a
supported pattern; to wait for a sub-task and get its value, use
[`await()`](Awaiting-Results.md) instead.

### Where do I report a bug or ask a question?

- Bugs: [open an issue](https://github.com/InitPHP/FiberLoops/issues) on the repo.
- Questions: the org-wide [Discussions](https://github.com/orgs/InitPHP/discussions).
- Security: see the [Security policy](https://github.com/InitPHP/.github/blob/main/SECURITY.md) — do not open a public issue.
