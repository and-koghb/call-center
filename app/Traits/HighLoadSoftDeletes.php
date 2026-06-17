<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HighLoadSoftDeletes
{
    use SoftDeletes;

    public static string $liveTimestamp = '1970-01-02 00:00:00';

    protected static function bootHighLoadSoftDeletes()
    {
        static::addGlobalScope(new class extends \Illuminate\Database\Eloquent\SoftDeletingScope {
            public function apply(Builder $builder, Model $model)
            {
                $builder->where($model->getQualifiedDeletedAtColumn(), '=', forward_static_call([get_class($model), 'getLiveTimestamp']));
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
