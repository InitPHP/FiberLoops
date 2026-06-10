<?php

/**
 * Loop.php
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

use function microtime;

/**
 * A minimal cooperative task scheduler built on PHP fibers.
 *
 * Queue tasks with {@see Loop::defer()} and drive them with {@see Loop::run()}.
 * Each task is a {@see Fiber}; the loop advances them round-robin, running each
 * until it yields ({@see Loop::next()} / {@see Loop::sleep()}) or terminates.
 *
 * Scheduling is cooperative: there is no preemption. A task keeps the loop until
 * it yields or returns, so long-running tasks must call {@see Loop::next()}
 * periodically to let siblings progress.
 *
 * ```php
 * $loop = new Loop();
 * $loop->defer(function () use ($loop) {
 *     foreach (range(1, 3) as $n) {
 *         echo "a$n";
 *         $loop->next();
 *     }
 * });
 * $loop->defer(function () use ($loop) {
 *     foreach (range(1, 3) as $n) {
 *         echo "b$n";
 *         $loop->next();
 *     }
 * });
 * $loop->run(); // a1b1a2b2a3b3
 * ```
 */
final class Loop implements LoopInterface
{
    /**
     * The round-robin queue of scheduled tasks.
     *
     * @var array<int, Fiber>
     */
    private array $queue = [];

    /**
     * {@inheritDoc}
     */
    public function defer(callable|Fiber $task): void
    {
        $this->queue[] = $task instanceof Fiber ? $task : new Fiber($task);
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        while ($this->queue !== []) {
            foreach ($this->queue as $id => $fiber) {
                $this->tick($id, $fiber);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function next(mixed $value = null): mixed
    {
        if (Fiber::getCurrent() === null) {
            throw new LoopException(
                'Loop::next() must be called from within a fiber, '
                . 'e.g. inside a task passed to Loop::defer() or Loop::await().',
            );
        }

        return Fiber::suspend($value);
    }

    /**
     * {@inheritDoc}
     */
    public function sleep(int|float $seconds): void
    {
        $until = microtime(true) + $seconds;
        while (microtime(true) < $until) {
            $this->next();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function await(callable|Fiber $task): mixed
    {
        $fiber = $task instanceof Fiber ? $task : new Fiber($task);

        if (!$fiber->isStarted()) {
            $fiber->start();
        }

        // When awaiting from inside a fiber we yield to the scheduler before each
        // step so sibling tasks keep running; the loop only ever resumes $fiber,
        // so it can never be terminated at the point we resume it.
        $insideFiber = Fiber::getCurrent() !== null;
        while (!$fiber->isTerminated()) {
            if ($insideFiber) {
                Fiber::suspend();
            }
            $fiber->resume();
        }

        return $fiber->getReturn();
    }

    /**
     * Advance a single queued task by one step.
     *
     * Starts it if it has not started, resumes it if it is suspended, or removes
     * it from the queue once it has terminated.
     *
     * @param int   $id    The task's key in the queue.
     * @param Fiber $fiber The task to advance.
     * @return void
     */
    private function tick(int $id, Fiber $fiber): void
    {
        if (!$fiber->isStarted()) {
            $fiber->start();
            return;
        }

        if (!$fiber->isTerminated()) {
            $fiber->resume();
            return;
        }

        unset($this->queue[$id]);
    }
}
