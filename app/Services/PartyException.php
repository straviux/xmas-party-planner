<?php

namespace App\Services;

use Exception;

class PartyException extends Exception
{
    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message, $status);
    }
}
