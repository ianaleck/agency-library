<?php

namespace Agency\Auth\Traits;

use Agency\Auth\ClerkService;
use Agency\Auth\Exceptions\AuthenticationException;
use Illuminate\Database\Eloquent\Model;

trait HasClerkId
{
    /**
     * Boot the trait
     */
    protected static function bootHasClerkId()
    {
        static::creating(function (Model $model) {
            if (!$model->clerk_id) {
                throw new AuthenticationException('Clerk ID is required');
            }
        });
    }

    /**
     * Get the Clerk user data
     *
     * @return array|null
     */
    public function getClerkData(): ?array
    {
        if (!$this->clerk_id) {
            return null;
        }

        /** @var ClerkService $clerk */
        $clerk = app(ClerkService::class);
        return $clerk->getUser($this->clerk_id);
    }

    /**
     * Sync permissions from Clerk
     *
     * @return void
     */
    public function syncClerkPermissions(): void
    {
        $clerkData = $this->getClerkData();
        if (!$clerkData) {
            return;
        }

        $permissions = $clerkData['public_metadata']['permissions'] ?? [];
        $this->clerk_metadata = array_merge(
            $this->clerk_metadata ?? [],
            ['permissions' => $permissions]
        );
        $this->save();
    }
}