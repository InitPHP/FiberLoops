# Testing

Two angles here: how the package itself is tested, and how to test *your* code that
uses the loop.

## Testing the package

FiberLoops ships a PHPUnit suite with **100% line coverage** of `src`, plus
PHPStan (level 8) and PHP-CS-Fixer (PER-CS). Composer exposes the usual scripts:

```bash
composer install

composer test        # run PHPUnit
composer stan        # run PHPStan
composer cs-check    # check PER-CS (no changes)
composer cs-fix      # apply PER-CS fixes
composer ci          # cs-check + stan + test, the full local gate
```

CI runs the same gate on PHP 8.2, 8.3, 8.4 and 8.5 (plus a lowest-dependency leg on 8.2)
on every push and pull request.

## Testing your own loop code

The loop is **fully deterministic**: with cooperative scheduling, the order in
which tasks interleave is fixed and reproducible — there is no timing or
thread-race to flake on. That makes loop-based code pleasant to test.

### Assert on order, not on output

The cleanest approach is to have your tasks append to a shared array and assert the
final sequence. No output buffering, no timing:

```php
use InitPHP\FiberLoops\Loop;
use PHPUnit\Framework\TestCase;

final class InterleavingTest extends TestCase
{
    public function test_two_tasks_take_turns(): void
    {
        $log = [];
        $loop = new Loop();

        $loop->defer(function () use ($loop, &$log) {
            foreach (['a1', 'a2'] as $s) {
                $log[] = $s;
                $loop->next();
            }
        });
        $loop->defer(function () use ($loop, &$log) {
            foreach (['b1', 'b2'] as $s) {
                $log[] = $s;
                $loop->next();
            }
        });

        $loop->run();

        $this->assertSame(['a1', 'b1', 'a2', 'b2'], $log);
    }
}
```

### Assert on a returned value

For code that uses `await()`, assert the value directly:

```php
public function test_await_returns_the_value(): void
{
    $loop = new Loop();

    $result = $loop->await(function () use ($loop) {
        $loop->next();
        return 42;
    });

    $this->assertSame(42, $result);
}
```

### Assert the misuse contract

`LoopException` is part of the contract, so it is worth asserting too:

```php
use InitPHP\FiberLoops\Exception\LoopException;

public function test_next_outside_a_fiber_throws(): void
{
    $this->expectException(LoopException::class);
    (new Loop())->next();
}
```

### Depend on `LoopInterface` for substitution

If a class of yours takes a loop, type-hint the **interface** so a test can pass a
double or an alternative scheduler:

```php
use InitPHP\FiberLoops\LoopInterface;

final class JobRunner
{
    public function __construct(private LoopInterface $loop) {}

    public function run(): void
    {
        $this->loop->defer(fn () => /* ... */);
        $this->loop->run();
    }
}

// In a test, pass a real Loop (it is lightweight and deterministic):
$runner = new JobRunner(new \InitPHP\FiberLoops\Loop());
```

In most cases the real `Loop` is the best "double" — it is tiny, has no I/O, and
runs deterministically, so there is rarely a reason to mock it.

## A note on timing-based assertions

Avoid asserting exact durations around `sleep()`. `microtime()` has microsecond
granularity and subtracting two large timestamps loses a little floating-point
precision, so a pause that is genuinely ≥ 0.05s can measure microscopically under
it. If you must assert a duration, use a generous tolerance (e.g. "at least 0.045s
for a 0.05s sleep") rather than an exact value.

## See also

- [Error Handling](Error-Handling.md) — the contract worth asserting.
- [API Reference](API-Reference.md) — the surface you are testing against.
