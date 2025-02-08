<?php

namespace Agency\Auth\Exceptions;

use Exception;

class PermissionException extends Exception
{
    protected $code = 403;
}