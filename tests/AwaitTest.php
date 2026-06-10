<?php

declare(strict_types=1);

namespace InitPHP\FiberLoops\Tests;

use Fiber;
use InitPHP\FiberLoops\Loop;
use PHPUnit\Framework\TestCase;

final class AwaitTest extends TestCase
{
    public function test_await_returns_the_tasks_value_from_the_main_context(): void
    {
        $loop = new Loop();

        $this->assertSame(42, $loop->await(fn (): int => 42));
    }

    public function test_await_returns_null_when_the_task_returns_nothing(): void
    {
        $loop = new Loop();

        $this->assertNull($loop->await(function (): void {
        }));
    }

    public function test_await_drives_a_task_that_suspends_once_from_the_main_context(): void
    {
        $loop = new Loop();
        $result = $loop->await(function () use ($loop): int {
            $loop->next();

            return 7;
        });

        $this->assertSame(7, $result);
    }

    /**
     * Regression for the bug where await() threw
     * "FiberError: Cannot suspend outside of a fiber" once the awaited task
     * suspended two or more times outside a fiber.
     */
    public function test_await_drives_a_task_that_suspends_many_times_from_the_main_context(): void
    {
        $loop = new Loop();
        $result = $loop->await(function () use ($loop): string {
            $loop->next();
            $loop->next();
            $loop->next();

            return 'done';
        });

        $this->assertSame('done', $result);
    }

    /**
     * Regression for the bug where await() unconditionally called start() and
     * threw "Cannot start a fiber that has already been started" for a Fiber
     * the caller had already started.
     */
    public function test_await_accepts_an_already_started_fiber(): void
    {
        $loop = new Loop();
        $fiber = new Fiber(function (): int {
            Fiber::suspend();
            Fiber::suspend();

            return 99;
        });
        $fiber->start();

        $this->assertSame(99, $loop->await($fiber));
    }

    public function test_await_accepts_an_unstarted_fiber_instance(): void
    {
        $loop = new Loop();
        $fiber = new Fiber(fn (): string => 'fiber-value');

        $this->assertSame('fiber-value', $loop->await($fiber));
    }

    public function test_await_inside_a_task_lets_sibling_tasks_progress(): void
    {
        $log = [];
        $loop = new Loop();
        $loop->defer(function () use ($loop, &$log): void {
            $log[] = 'A:before';
            $result = $loop->await(function () use ($loop, &$log): string {
                $log[] = 'inner1';
                $loop->next();
                $log[] = 'inner2';
                $loop->next();

                return 'R';
            });
            $log[] = 'A:after=' . $result;
        });
        $loop->defer(function () use ($loop, &$log): void {
            foreach (['B1', 'B2', 'B3'] as $step) {
                $log[] = $step;
                $loop->next();
            }
        });
        $loop->run();

        $this->assertSame(
            ['A:before', 'inner1', 'B1', 'inner2', 'B2', 'A:after=R', 'B3'],
            $log,
        );
    }

    public function test_await_inside_a_task_returns_the_value(): void
    {
        $captured = null;
        $loop = new Loop();
        $loop->defer(function () use ($loop, &$captured): void {
            $captured = $loop->await(function () use ($loop): int {
                $loop->next();

                return 123;
            });
        });
        $loop->run();

        $this->assertSame(123, $captured);
    }

    public function test_nested_await(): void
    {
        $loop = new Loop();
        $result = $loop->await(function () use ($loop): int {
            $inner = $loop->await(function () use ($loop): int {
                $loop->next();

                return 20;
            });

            return $inner + 1;
        });

        $this->assertSame(21, $result);
    }
}
