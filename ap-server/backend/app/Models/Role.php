<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['scope_layer', 'permission_role', 'slug', 'name'])]
class Role extends BaseModel
{
    use HasBooleanSoftDeletes;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->wherePivot('is_deleted', false)
            ->withPivot(['created_by', 'updated_by', 'is_deleted'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<UserRoleAssignment, $this>
     */
    public function userRoleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }
}
