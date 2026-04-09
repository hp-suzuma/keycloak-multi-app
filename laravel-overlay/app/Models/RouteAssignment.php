<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteAssignment extends Model
{
    protected $fillable = [
        'sub',
        'display_name',
        'site_code',
        'server_url',
        'is_active',
        'priority',
        'notes',
        'last_resolved_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_resolved_at' => 'datetime',
    ];
}
