<?php
/**
 * Loop.php
 *
 * This file is part of FiberLoops.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\FiberLoops;

use \Fiber;

use function microtime;

class Loop
{

    public function __construct(protected array $callStack = [])
    {
    }

    public function next(mixed $value = null)
    {
        return Fiber::suspend($value);
    }

    public function sleep(int|float $seconds)
    {
        $stop = microtime(true) + (float)$seconds;
        while (microtime(true) < $stop) {
            $this->next();
        }
    }

    public function await(callable|Fiber $fiber): mixed
    {
        if(!($fiber instanceof Fiber)){
            $fiber = new Fiber($fiber);
        }
        $fiber->start();
        while ($fiber->isTerminated() === FALSE) {
            $fiber->resume();

            if(!$fiber->isTerminated()){
                Fiber::suspend();
            }else{
                break;
            }
        }
        return $fiber->getReturn();
    }

    public function defer(callable|Fiber $fiber): void
    {
        $this->callStack[] = ($fiber instanceof Fiber) ? $fiber : new Fiber($fiber);
    }

    public function run()
    {
        while ($this->callStack != []) {
            foreach ($this->callStack as $id => $fiber) {
                $this->callFiber($id, $fiber);
            }
        }
    }

    protected function callFiber(int $id, Fiber $fiber)
    {
        if($fiber->isStarted() === FALSE){
            return $fiber->start($id);
        }

        if($fiber->isTerminated() === FALSE){
            return $fiber->resume();
        }

        unset($this->callStack[$id]);
        return $fiber->getReturn();
    }

}
