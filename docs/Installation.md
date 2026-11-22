# Installation

## Requirements

- **PHP 8.2 or later.** [Fibers](https://www.php.net/manual/en/language.fibers.php)
  were added to the language in PHP 8.1, and FiberLoops is built directly on them;
  the package itself requires PHP 8.2 or later.
  There is **no extension to install** — fibers are part of core PHP.

You can confirm your version with:

```bash
php -v
```

## Install with Composer

```bash
composer require initphp/fiber-loops
```

That is the whole dependency footprint: the package requires nothing but PHP
itself.

## Autoloading

FiberLoops ships a PSR-4 autoloader under the `InitPHP\FiberLoops\` namespace.
Once installed, pull in Composer's autoloader and you are ready:

```php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;

$loop = new Loop();
```

The public classes are:

| Class | Purpose |
| ----- | ------- |
| `InitPHP\FiberLoops\Loop` | The scheduler. |
| `InitPHP\FiberLoops\LoopInterface` | The contract `Loop` implements (depend on this for inversion / mocking). |
| `InitPHP\FiberLoops\Exception\LoopException` | Thrown when the loop is misused (e.g. yielding outside a fiber). |

## Verify the install

Save this as `smoke.php` next to your `vendor/` directory and run it:

```php
<?php
require_once 'vendor/autoload.php';

use InitPHP\FiberLoops\Loop;

$loop = new Loop();
$loop->defer(function () {
    echo "FiberLoops is working.\n";
});
$loop->run();
```

```bash
php smoke.php
```

```text
FiberLoops is working.
```

If you see that line, you are ready for the [Quick Start](Quick-Start.md).

## Versioning

The package follows [Semantic Versioning](https://semver.org). The current
`main` branch requires PHP 8.2+ (support for PHP 8.1 was dropped) and exposes the
API documented throughout this wiki. See the
[Changelog](https://github.com/InitPHP/FiberLoops/blob/main/CHANGELOG.md) for what
changed between releases.
