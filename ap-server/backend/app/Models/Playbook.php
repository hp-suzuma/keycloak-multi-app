<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Object\ObjectCodeNormalizer;

#[Fillable(['scope_id', 'code', 'name'])]
class Playbook extends Model
{
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = app(ObjectCodeNormalizer::class)->normalize($value);
    }

    /**
     * @return BelongsTo<Scope, $this>
     */
    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}
