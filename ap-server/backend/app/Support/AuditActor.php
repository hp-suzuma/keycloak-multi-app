<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class AuditActor
{
    public static function name(): string
    {
        return Auth::user()?->name ?? 'system';
    }
}
