<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'name'])]
class Permission extends BaseModel
{
    use HasBooleanSoftDeletes;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->wherePivot('is_deleted', false)
            ->withPivot(['created_by', 'updated_by', 'is_deleted'])
            ->withTimestamps();
    }
}
