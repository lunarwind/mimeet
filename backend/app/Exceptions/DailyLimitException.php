<?php

namespace App\Exceptions;

use Exception;

class DailyLimitException extends Exception
{
    public function __construct(string $message = '今日訊息已達上限（30則）')
    {
        parent::__construct($message, 429);
    }
}
