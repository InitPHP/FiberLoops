<?php

declare(strict_types=1);

namespace InitPHP\FiberLoops\Tests;

use InitPHP\FiberLoops\Exception\LoopException;
use InitPHP\FiberLoops\Loop;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NextAndSleepTest extends TestCase
{
    public function test_next_outside_a_fiber_throws_loop_exception(): void
    {
        $loop = new Loop();

        $this->expectException(LoopException::class);
        $loop->next();
    }

    public function test_loop_exception_is_a_runtime_exception(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new LoopException('x'));
    }

    public function test_next_returns_null_under_the_bundled_scheduler(): void
    {
        $returned = 'unset';
        $loop = new Loop();
        $loop->defer(function () use ($loop, &$returned): void {
            $returned = $loop->next('ignored-by-scheduler');
        });
        $loop->run();

        $this->assertNull($returned);
    }

    public function test_sleep_outside_a_fiber_throws_loop_exception(): void
    {
        $loop = new Loop();

        $this->expectException(LoopException::class);
        $loop->sleep(0.01);
    }

    public function test_sleep_zero_is_an_immediate_no_op_even_outside_a_fiber(): void
    {
        $loop = new Loop();
        $loop->sleep(0);

        $this->assertTrue(true, 'sleep(0) never yields, so it never touches a fiber.');
    }

    public function test_sleep_pauses_the_current_task_while_a_sibling_runs(): void
    {
        $log = [];
        $loop = new Loop();
        $loop->defer(function () use ($loop, &$log): void {
            $loop->sleep(0.05);
            $log[] = 'slept';
        });
        $loop->defer(function () use ($loop, &$log): void {
            foreach (range(1, 3) as $n) {
                $log[] = "fast$n";
                $loop->next();
            }
        });
        $loop->run();

        $this->assertSame('slept', end($log), 'The sleeping task finishes after its sibling.');
        $this->assertContains('fast1', $log);
        $this->assertContains('fast3', $log);
    }

    public function test_sleep_blocks_for_approximately_the_requested_duration(): void
    {
        $loop = new Loop();
        $elapsed = 0.0;
        $loop->defer(function () use ($loop, &$elapsed): void {
            $start = microtime(true);
            $loop->sleep(0.05);
            $elapsed = microtime(true) - $start;
        });
        $loop->run();

        // A few milliseconds of tolerance: microtime() has ~1us granularity and
        // subtracting two large epoch doubles loses ~1e-7 of precision, so an
        // elapsed that is genuinely >= 0.05 can read microscopically under it.
        $this->assertGreaterThanOrEqual(0.045, $elapsed, 'sleep() should pause for roughly the requested time.');
        $this->assertLessThan(1.0, $elapsed, 'sleep() should not overshoot wildly.');
    }
}
