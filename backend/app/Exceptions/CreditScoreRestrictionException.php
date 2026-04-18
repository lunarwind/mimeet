<?php

namespace App\Exceptions;

use Exception;

class CreditScoreRestrictionException extends Exception
{
    public function __construct(string $message = '誠信分數不足，無法向較高分數的用戶發送訊息')
    {
        parent::__construct($message, 403);
    }
}
