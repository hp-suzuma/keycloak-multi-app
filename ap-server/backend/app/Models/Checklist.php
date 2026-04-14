<?php

namespace App\Models;

use App\Services\Object\ObjectCodeNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scope_id', 'code', 'name'])]
class Checklist extends Model
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
