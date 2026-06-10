<?php

declare(strict_types=1);

namespace InitPHP\FiberLoops\Tests;

use Fiber;
use InitPHP\FiberLoops\Loop;
use InitPHP\FiberLoops\LoopInterface;
use PHPUnit\Framework\TestCase;

final class LoopSchedulerTest extends TestCase
{
    public function test_loop_implements_the_contract(): void
    {
        $this->assertInstanceOf(LoopInterface::class, new Loop());
    }

    public function test_running_an_empty_loop_is_a_no_op(): void
    {
        $loop = new Loop();
        $loop->run();

        $this->assertTrue(true, 'run() on an empty queue returns without error.');
    }

    public function test_a_single_task_runs_to_completion(): void
    {
        $log = [];
        $loop = new Loop();
        $loop->defer(function () use (&$log): void {
            $log[] = 'started';
            $log[] = 'finished';
        });
        $loop->run();

        $this->assertSame(['started', 'finished'], $log);
    }

    public function test_two_tasks_interleave_round_robin(): void
    {
        $log = [];
        $loop = new Loop();
        $loop->defer(function () use ($loop, &$log): void {
            foreach (['a1', 'a2', 'a3'] as $step) {
                $log[] = $step;
                $loop->next();
            }
        });
        $loop->defer(function () use ($loop, &$log): void {
            foreach (['b1', 'b2', 'b3'] as $step) {
                $log[] = $step;
                $loop->next();
            }
        });
        $loop->run();

        $this->assertSame(['a1', 'b1', 'a2', 'b2', 'a3', 'b3'], $log);
    }

    public function test_readme_example_one_output(): void
    {
        $loop = new Loop();
        $loop->defer(function () use ($loop): void {
            foreach (range(0, 5) as $value) {
                echo $value . PHP_EOL;
                $loop->next();
            }
        });
        $loop->defer(function () use ($loop): void {
            foreach (range(6, 9) as $value) {
                echo $value . PHP_EOL;
                $loop->next();
            }
        });

        $this->expectOutputString("0\n6\n1\n7\n2\n8\n3\n9\n4\n5\n");
        $loop->run();
    }

    public function test_defer_accepts_a_fiber_instance(): void
    {
        $log = [];
        $loop = new Loop();
        $loop->defer(new Fiber(function () use (&$log): void {
            $log[] = 'from-fiber';
        }));
        $loop->run();

        $this->assertSame(['from-fiber'], $log);
    }

    public function test_a_task_deferred_during_run_is_picked_up(): void
    {
        $log = [];
        $loop = new Loop();
        $loop->defer(function () use ($loop, &$log): void {
            $log[] = 'outer-start';
            $loop->defer(function () use (&$log): void {
                $log[] = 'inner';
            });
            $loop->next();
            $log[] = 'outer-end';
        });
        $loop->run();

        $this->assertSame(['outer-start', 'outer-end', 'inner'], $log);
    }

    public function test_deferred_callable_receives_no_arguments(): void
    {
        $received = null;
        $loop = new Loop();
        $loop->defer(function (mixed ...$args) use (&$received): void {
            $received = $args;
        });
        $loop->run();

        $this->assertSame([], $received, 'No internal task id leaks into the callable.');
    }

    public function test_an_exception_thrown_in_a_task_propagates_out_of_run(): void
    {
        $loop = new Loop();
        $loop->defer(function (): void {
            throw new \RuntimeException('boom');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $loop->run();
    }

    public function test_tasks_of_uneven_length_all_complete(): void
    {
        $log = [];
        $loop = new Loop();
        $loop->defer(function () use ($loop, &$log): void {
            $log[] = 'short';
        });
        $loop->defer(function () use ($loop, &$log): void {
            foreach (range(1, 3) as $n) {
                $log[] = "long$n";
                $loop->next();
            }
        });
        $loop->run();

        $this->assertSame(['short', 'long1', 'long2', 'long3'], $log);
    }
}
