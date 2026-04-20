<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasBooleanSoftDeletes
{
    public static function bootHasBooleanSoftDeletes(): void
    {
        static::addGlobalScope('boolean_soft_deletes', function (Builder $builder) {
            $builder->where($builder->qualifyColumn('is_deleted'), false);
        });
    }

    public function initializeHasBooleanSoftDeletes(): void
    {
        $this->casts['is_deleted'] = 'boolean';
    }

    public function delete()
    {
        if ($this->is_deleted) {
            return true;
        }

        $this->is_deleted = true;

        return $this->save();
    }

    public function restore(): bool
    {
        if (! $this->is_deleted) {
            return true;
        }

        $this->is_deleted = false;

        return $this->save();
    }

    public function forceDelete()
    {
        return parent::delete();
    }

    public function trashed(): bool
    {
        return (bool) $this->is_deleted;
    }

    public function scopeWithDeleted(Builder $query): Builder
    {
        return $query->withoutGlobalScope('boolean_soft_deletes');
    }

    public function scopeOnlyDeleted(Builder $query): Builder
    {
        return $query
            ->withoutGlobalScope('boolean_soft_deletes')
            ->where($this->qualifyColumn('is_deleted'), true);
    }
}
