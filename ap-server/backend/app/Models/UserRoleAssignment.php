<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['keycloak_sub', 'role_id', 'scope_id'])]
class UserRoleAssignment extends BaseModel
{
    use HasBooleanSoftDeletes;

    /**
     * @return BelongsTo<ApUser, $this>
     */
    public function apUser(): BelongsTo
    {
        return $this->belongsTo(ApUser::class, 'keycloak_sub', 'keycloak_sub');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Scope, $this>
     */
    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}
