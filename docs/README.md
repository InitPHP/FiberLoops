# FiberLoops documentation

FiberLoops is a minimal cooperative task scheduler built on PHP fibers. These
guides take you from a first example to the precise semantics of every method.

| Guide | What it covers |
| ----- | -------------- |
| [Getting started](getting-started.md) | Install, your first two tasks, how the loop runs them. |
| [Concepts](concepts.md) | The scheduling model: fibers, the round-robin queue, cooperative yielding. |
| [API reference](api/README.md) | Every method, its signature, behaviour and edge cases. |
| [await & concurrency](await-and-concurrency.md) | Awaiting sub-tasks from the main context and from inside a task. |
| [Caveats & gotchas](caveats.md) | Busy-wait `sleep()`, in-fiber preconditions, non-preemptive scheduling. |

Every code sample in these docs is a complete, runnable script (prepend
`require 'vendor/autoload.php';` and `use InitPHP\FiberLoops\Loop;`). The output
shown beneath each one is the program's real output.
