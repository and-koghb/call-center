<?php

namespace App\Models;

use App\Enums\OperatorStatus;
use App\Observers\OperatorObserver;
use App\Traits\HighLoadSoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(OperatorObserver::class)]
class Operator extends Model
{
    use HasFactory, HighLoadSoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'last_call_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_call_at' => 'datetime',
            'status' => OperatorStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
