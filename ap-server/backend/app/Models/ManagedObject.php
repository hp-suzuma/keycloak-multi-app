<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Object\ObjectCodeNormalizer;

#[Fillable(['scope_id', 'code', 'name'])]
class ManagedObject extends BaseModel
{
    use HasBooleanSoftDeletes;

    protected $table = 'objects';

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
