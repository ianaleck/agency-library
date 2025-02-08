<?php

namespace Agency\Auth\Traits;

trait HasPermissions
{
    /**
     * Check if the user has a specific permission
     *
     * @param string|array $permissions
     * @return bool
     */
    public function hasPermission(string|array $permissions): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $userPermissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the user has any of the given permissions
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        $userPermissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user permissions
     *
     * @return array
     */
    public function getPermissions(): array
    {
        return $this->clerk_metadata['permissions'] ?? [];
    }
}