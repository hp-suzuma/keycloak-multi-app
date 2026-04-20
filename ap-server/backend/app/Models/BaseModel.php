<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

abstract class BaseModel extends Model
{
    use HasAuditColumns;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    public function freshTimestamp(): Carbon
    {
        return Carbon::now();
    }

    protected function performInsert(Builder $query): bool
    {
        $this->applyAuditColumnsForInsert();

        return parent::performInsert($query);
    }

    protected function performUpdate(Builder $query): bool
    {
        $this->applyAuditColumnsForUpdate();

        return parent::performUpdate($query);
    }

    protected function asDateTime($value): Carbon
    {
        try {
            return parent::asDateTime($value);
        } catch (InvalidArgumentException) {
            $normalized = (new DateTime((string) $value))->format($this->getDateFormat());

            return Carbon::createFromFormat($this->getDateFormat(), $normalized);
        }
    }
}
