<?php

namespace App\Exceptions;

class InsufficientPointsException extends \RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $current)
    {
        parent::__construct("點數不足：需要 {$required} 點，目前 {$current} 點");
    }
}
