<?php

/**
 * LoopException.php
 *
 * This file is part of FiberLoops.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\FiberLoops\Exception;

use RuntimeException;

/**
 * Raised when the loop is driven in a way its cooperative model does not allow.
 *
 * The most common case is calling a suspending operation ({@see \InitPHP\FiberLoops\Loop::next()}
 * or {@see \InitPHP\FiberLoops\Loop::sleep()}) from outside a fiber. PHP would
 * otherwise surface a bare {@see \FiberError}; this exception wraps that
 * precondition in a package-namespaced type with an actionable message.
 */
class LoopException extends RuntimeException
{
}
