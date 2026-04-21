<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['layer', 'code', 'name', 'parent_scope_id'])]
class Scope extends BaseModel
{
    use HasBooleanSoftDeletes;

    /**
     * @return BelongsTo<Scope, $this>
     */
    public function parentScope(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_scope_id');
    }

    /**
     * @return HasMany<Scope, $this>
     */
    public function childScopes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_scope_id');
    }

    /**
     * @return HasMany<UserRoleAssignment, $this>
     */
    public function userRoleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    /**
     * @return HasMany<ManagedObject, $this>
     */
    public function managedObjects(): HasMany
    {
        return $this->hasMany(ManagedObject::class, 'scope_id');
    }
}
