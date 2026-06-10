# Changelog

All notable changes to `initphp/fiber-loops` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-06-10

### Changed (breaking)

- **`Loop::__construct()` no longer takes a `$callStack` array.** The constructor
  is now parameterless; tasks are added exclusively through `defer()` (and
  `await()`). The previous signature accepted an arbitrary `array`, which deferred
  a `TypeError` to `run()` whenever the array held anything other than a `Fiber`.

  ```php
  // before
  $loop = new Loop($preBuiltFibers);
  // after
  $loop = new Loop();
  foreach ($preBuiltFibers as $fiber) {
      $loop->defer($fiber);
  }
  ```

- **Deferred callables no longer receive an argument.** A task's internal queue id
  was previously passed as the first argument to the callable (`$fiber->start($id)`).
  Callables are now started with no arguments.

### Fixed

- **`await()` no longer throws `FiberError: Cannot suspend outside of a fiber`.**
  When called from the main context, it now drives the awaited task to completion
  synchronously regardless of how many times that task yields. Previously it
  worked only if the task yielded at most once and threw otherwise.
- **`await()` now accepts an already-started `Fiber`.** It previously called
  `start()` unconditionally and threw `Cannot start a fiber that has already been
  started`, despite its `callable|Fiber` signature.
- **`await()` interleaves fairly when called from inside a task.** It now yields to
  the scheduler before each step of the awaited task, so a sibling task is no
  longer skipped for the first two steps.

### Added

- `LoopInterface` — the public contract for the scheduler, for dependency
  inversion and test doubles.
- `Exception\LoopException` (a `RuntimeException`) — thrown with an actionable
  message when `next()` or `sleep()` is called outside a fiber, instead of PHP's
  bare `FiberError`.
- Full PHPDoc on every public method, derived from verified runtime behaviour.
- A PHPUnit test suite with 100% line coverage of `src`.
- Tooling: `phpunit.xml`, `phpstan.neon` (level 8), `.php-cs-fixer.php` (PSR-12),
  `.gitattributes`, composer `scripts` (`test`, `stan`, `cs-check`, `cs-fix`, `ci`).
- A GitHub Actions CI workflow (validate → CS-Fixer → PHPStan → PHPUnit on PHP
  8.1–8.4 + coverage).
- English documentation under `docs/`.

### Internal

- Standardised the source on PSR-12 (brace and spacing style, lowercase
  `true`/`false`, strict comparisons, full return types).
- Renamed the internal task store from `$callStack` to `$queue` — it is a
  round-robin queue, not a stack.
