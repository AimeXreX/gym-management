<?php

namespace App\Core\Tenancy\Exceptions;

use RuntimeException;

class MissingGymContext extends RuntimeException
{
    public static function make(): self
    {
        return new self('A valid gym context is required for this operation.');
    }
}
