<?php

namespace Agency\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array|null getCurrentUser()
 * @method static bool verifySession()
 * @method static array|null getUser(string $userId)
 * @method static array validateToken(string $token)
 * @method static bool checkPermissions(array|string $permissions)
 * 
 * @see \Agency\Auth\ClerkService
 */
class Auth extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'agency.auth';
    }
}