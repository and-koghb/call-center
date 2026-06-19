<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HighLoadSoftDeletes
{
    use SoftDeletes {
        bootSoftDeletes as private traitBootSoftDeletes;
    }

    public static string $liveTimestamp = '1970-01-02 00:00:00';

    public static function bootSoftDeletes(): void
    {
    }

    protected static function bootHighLoadSoftDeletes(): void
    {
        static::addGlobalScope('highLoadSoftDeletes', new class implements \Illuminate\Database\Eloquent\Scope {
            public function apply(Builder $builder, Model $model): void
            {
                $builder->where(
                    $model->getQualifiedDeletedAtColumn(),
                    '=',
                    forward_static_call([get_class($model), 'getLiveTimestamp'])
                );
            }
        });
    }

    public function restore()
    {
        $this->{$this->getDeletedAtColumn()} = static::$liveTimestamp;
        $result = $this->save();
        $this->fireModelEvent('restored', false);
        return $result;
    }

    public function getDeletedAtAttribute($value)
    {
        if ($value === static::$liveTimestamp || is_null($value)) {
            return null;
        }
        return $this->asDateTime($value);
    }

    public static function getLiveTimestamp(): string
    {
        return static::$liveTimestamp;
    }
}
