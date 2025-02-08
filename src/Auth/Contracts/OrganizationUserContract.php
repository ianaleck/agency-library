<?php

namespace Agency\Auth\Contracts;

interface OrganizationUserContract
{
    /**
     * Check if the user has a specific permission in this organization
     */
    public function hasPermission(string $permission): bool;

    /**
     * Check if the user has a specific role in this organization
     */
    public function hasRole(string $role): bool;

    /**
     * Check if the user has any of the given roles in this organization
     */
    public function hasAnyRole(array $roles): bool;
}