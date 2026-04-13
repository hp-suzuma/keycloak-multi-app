<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['keycloak_sub', 'role_id', 'scope_id'])]
class UserRoleAssignment extends Model
{
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
