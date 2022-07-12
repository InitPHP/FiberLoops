# InitPHP Fiber Loops

PHP Fiber Loop

![php-fiber](https://user-images.githubusercontent.com/104234499/178588669-e6a6384b-5712-45ec-9676-fe8900fd625f.png)


## Requirements

- PHP 8.1 or later

## Installation

```
composer require initphp/fiber-loops
```

## Usage

```php
require_once "vendor/autoload.php";
use InitPHP\FiberLoops\Loop;

$loop = new Loop();

$loop->defer(function () use ($loop) {
    foreach (range(0, 5) as $value) {
        echo $value . PHP_EOL;
        $loop->next();
    }
});

$loop->defer(function () use ($loop) {
    foreach (range(6, 9) as $value) {
        echo $value . PHP_EOL;
        $loop->next();
    }
});

$loop->run();
```

_Output :_

```
0
6
1
7
2
8
3
9
4
5
```

_**Example 2 :**_

```php
require_once "vendor/autoload.php";
use InitPHP\FiberLoops\Loop;

$loop = new Loop();

$loop->defer(function () use ($loop) {
    $loop->sleep(0.2);
    foreach (range(0, 5) as $value) {
        echo $value . PHP_EOL;
    }
});

$loop->defer(function () use ($loop) {
    foreach (range(6, 9) as $value) {
        echo $value . PHP_EOL;
    }
});

$loop->run();
```

_Output :_

```
6
7
8
9
0
1
2
3
4
5
```

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
