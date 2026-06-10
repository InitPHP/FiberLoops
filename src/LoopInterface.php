<?php

/**
 * LoopInterface.php
 *
 * This file is part of FiberLoops.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\FiberLoops;

use Fiber;
use InitPHP\FiberLoops\Exception\LoopException;

/**
 * Contract for a cooperative, fiber-based task scheduler.
 *
 * A loop holds a queue of tasks (PHP {@see Fiber}s) and advances them
 * round-robin: on every pass each task is run until it cooperatively yields
 * (by calling {@see LoopInterface::next()}) or terminates. Scheduling is
 * cooperative, not preemptive — a task that never yields will run to completion
 * before any sibling task is touched.
 *
 * Depend on this interface rather than the concrete {@see Loop} when you want to
 * swap scheduling strategies or substitute a double in tests.
 */
interface LoopInterface
{
    /**
     * Queue a task to be run by {@see LoopInterface::run()}.
     *
     * A callable is wrapped in a new {@see Fiber}; a {@see Fiber} is queued as
     * given. Tasks may be deferred at any time, including from inside a running
     * task — a task added during {@see LoopInterface::run()} is picked up on the
     * next scheduling pass.
     *
     * @param callable|Fiber $task The work to schedule. A callable receives no
     *                             arguments.
     * @return void
     */
    public function defer(callable|Fiber $task): void;

    /**
     * Run every queued task to completion.
     *
     * Blocks until the queue is empty, advancing each task one step per pass.
     * A task that never yields and never returns will block the loop forever.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Cooperatively yield control from the current task back to the scheduler.
     *
     * Must be called from within a fiber (i.e. from inside a deferred or awaited
     * task); otherwise a {@see LoopException} is thrown.
     *
     * The bundled scheduler resumes tasks without a value, so the return value
     * is `null` under {@see LoopInterface::run()} and {@see LoopInterface::await()}.
     * The `$value` argument is the value yielded to whatever drives the fiber and
     * is ignored by the bundled scheduler; it exists for custom drivers.
     *
     * @param mixed $value Value to yield to the driver. Ignored by this scheduler.
     * @return mixed The value the driver passed to `Fiber::resume()`; `null` here.
     * @throws LoopException If called outside a fiber.
     */
    public function next(mixed $value = null): mixed;

    /**
     * Cooperatively pause the current task for at least the given duration.
     *
     * Implemented as a busy-wait that yields once per iteration: sibling tasks
     * keep making progress, but the loop does not idle the CPU. A non-positive
     * duration returns immediately without yielding.
     *
     * Must be called from within a fiber; otherwise a {@see LoopException} is
     * thrown (it yields via {@see LoopInterface::next()}).
     *
     * @param int|float $seconds Seconds to pause. Non-positive returns at once.
     * @return void
     * @throws LoopException If called outside a fiber.
     */
    public function sleep(int|float $seconds): void;

    /**
     * Run a task to completion and return its value.
     *
     * Accepts a callable (wrapped in a new {@see Fiber}) or a {@see Fiber},
     * started or not. When called from inside a fiber, the calling task
     * cooperatively yields while the awaited task makes progress, so sibling
     * tasks continue to run. When called from the main context, the awaited task
     * is driven to completion synchronously.
     *
     * @param callable|Fiber $task The task to await.
     * @return mixed The awaited task's return value (`null` if it returns none).
     */
    public function await(callable|Fiber $task): mixed;
}
