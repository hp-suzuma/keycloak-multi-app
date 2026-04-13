<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['layer', 'code', 'name', 'parent_scope_id'])]
class Scope extends Model
{
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
}
