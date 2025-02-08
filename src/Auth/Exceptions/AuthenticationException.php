<?php

namespace Agency\Auth\Exceptions;

use Exception;

class AuthenticationException extends Exception
{
    protected $code = 401;
}