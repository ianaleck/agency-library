<?php

namespace Agency\Auth\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasClerkOrganization
{
    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Users belonging to this organization
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('agency.auth.user_model'))
            ->using(config('agency.auth.organization_user_model'))
            ->withPivot('role', 'permissions')
            ->withTimestamps();
    }

    /**
     * Add a user to the organization
     */
    public function addMember($user, string $role = null, array $permissions = []): void
    {
        $this->users()->attach($user, [
            'role' => $role,
            'permissions' => json_encode($permissions)
        ]);
    }

    /**
     * Remove a user from the organization
     */
    public function removeMember($user): void
    {
        $this->users()->detach($user);
    }

    /**
     * Update a member's role
     */
    public function updateMemberRole($user, string $role): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'role' => $role
        ]);
    }

    /**
     * Get a member's role
     */
    public function getMemberRole($user): ?string
    {
        $membership = $this->users()->where('users.id', $user->id)->first();
        return $membership?->pivot->role;
    }

    /**
     * Get a member's permissions
     */
    public function getMemberPermissions($user): array
    {
        $membership = $this->users()->where('users.id', $user->id)->first();
        return json_decode($membership?->pivot->permissions ?? '[]', true);
    }
}