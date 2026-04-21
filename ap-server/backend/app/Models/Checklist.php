<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanSoftDeletes;
use App\Services\Object\ObjectCodeNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['scope_id', 'code', 'name'])]
class Checklist extends BaseModel
{
    use HasBooleanSoftDeletes;

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
