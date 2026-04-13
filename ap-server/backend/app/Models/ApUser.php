<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['keycloak_sub', 'display_name', 'email'])]
class ApUser extends Model
{
    protected $table = 'ap_users';

    protected $primaryKey = 'keycloak_sub';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return HasMany<UserRoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class, 'keycloak_sub', 'keycloak_sub');
    }
}
